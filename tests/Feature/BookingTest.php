<?php

use App\Actions\Bookings\HandleStripeWebhook;
use App\DataTransferObjects\BookingData;
use App\DataTransferObjects\StripeCheckoutData;
use App\DataTransferObjects\StripeRefundData;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourStartDate;
use App\Models\User;
use App\Services\Payments\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function fakeStripeGateway(string $refundStatus = 'succeeded'): object
{
    $fake = new class($refundStatus) implements StripeGateway
    {
        public array $checkouts = [];

        public array $expired = [];

        public array $refunds = [];

        public array $checkoutSessions = [];

        public function __construct(private readonly string $refundStatus) {}

        public function createCheckout(Booking $booking): StripeCheckoutData
        {
            $this->checkouts[] = $booking->id;
            $id = 'cs_test_'.$booking->id;
            $this->checkoutSessions[$id] = [
                'id' => $id,
                'status' => 'open',
                'payment_status' => 'unpaid',
                'client_reference_id' => $booking->id,
                'amount_total' => $booking->total_amount,
                'currency' => $booking->currency,
                'payment_intent' => null,
                'metadata' => ['booking_id' => $booking->id, 'booking_reference' => $booking->reference],
            ];

            return new StripeCheckoutData($id, 'https://checkout.stripe.test/'.$booking->id, $booking->hold_expires_at->timestamp);
        }

        public function expireCheckout(string $sessionId): void
        {
            if (($this->checkoutSessions[$sessionId]['payment_status'] ?? null) === 'paid') {
                throw new RuntimeException('A paid Checkout Session cannot be expired.');
            }

            $this->expired[] = $sessionId;
            $this->checkoutSessions[$sessionId]['status'] = 'expired';
        }

        public function retrieveCheckout(string $sessionId): array
        {
            return $this->checkoutSessions[$sessionId];
        }

        public function createRefund(Booking $booking): StripeRefundData
        {
            $this->refunds[] = $booking->id;

            return new StripeRefundData('re_test_'.$booking->id.'_'.$booking->refund_attempt, $this->refundStatus);
        }

        public function retrieveRefund(string $refundId): StripeRefundData
        {
            return new StripeRefundData($refundId, $this->refundStatus);
        }

        public function constructEvent(string $payload, string $signature): array
        {
            return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        }
    };
    app()->instance(StripeGateway::class, $fake);

    return $fake;
}

function bookingDeparture(array $tourAttributes = [], array $dateAttributes = []): TourStartDate
{
    $tour = Tour::factory()->create(['max_group_size' => 10, 'price' => 100, 'price_discount_percent' => null, ...$tourAttributes]);

    return TourStartDate::factory()->for($tour)->create([
        'start_datetime_utc' => now('UTC')->addDays(10), 'available_spots' => 10, 'is_active' => true, ...$dateAttributes,
    ]);
}

function bookingPayload(TourStartDate $departure, int $quantity = 1): array
{
    return [
        'tour_start_date_id' => $departure->id,
        'travelers' => collect(range(1, $quantity))->map(fn (int $number): array => [
            'full_name' => "Traveler {$number}", 'email' => "traveler{$number}@example.com", 'phone' => '+12025550123',
        ])->all(),
    ];
}

it('requires authentication verification and an idempotency key', function () {
    fakeStripeGateway();
    $departure = bookingDeparture();
    $payload = bookingPayload($departure);
    $this->postJson('/api/v1/bookings', $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())
        ->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => Str::uuid()->toString()])->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson('/api/v1/bookings', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
    $this->postJson('/api/v1/bookings', [...$payload, 'total_amount' => 1], ['Idempotency-Key' => Str::uuid()->toString()])
        ->assertUnprocessable()->assertJsonValidationErrors('total_amount');
});

it('holds seats and creates a cent-accurate Stripe checkout', function () {
    $stripe = fakeStripeGateway();
    $user = User::factory()->create();
    $departure = bookingDeparture(['price' => '100.05', 'price_discount_percent' => '12.50']);
    $response = $this->actingAs($user)->postJson('/api/v1/bookings', bookingPayload($departure, 2), [
        'Idempotency-Key' => Str::uuid()->toString(),
    ])->assertCreated()->assertHeader('Cache-Control')
        ->assertJsonPath('data.attributes.status', 'pending_payment')
        ->assertJsonPath('data.attributes.unit_amount', 8754)
        ->assertJsonPath('data.attributes.total_amount', 17508);

    expect($departure->fresh()->available_spots)->toBe(8)
        ->and($departure->fresh()->reserved_spots)->toBe(2)
        ->and($stripe->checkouts)->toHaveCount(1)
        ->and($response->json('data.attributes.checkout_url'))->toStartWith('https://checkout.stripe.test/');
});

it('replays an identical idempotent request and rejects changed input', function () {
    $stripe = fakeStripeGateway();
    $user = User::factory()->create();
    $departure = bookingDeparture();
    $key = Str::uuid()->toString();
    $payload = bookingPayload($departure);
    $first = $this->actingAs($user)->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => $key])->assertCreated();
    $this->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => $key])->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));
    $this->postJson('/api/v1/bookings', bookingPayload($departure, 2), ['Idempotency-Key' => $key])->assertConflict();
    expect($stripe->checkouts)->toHaveCount(1)->and($departure->fresh()->available_spots)->toBe(9);
});

it('confirms free bookings without Stripe', function () {
    $stripe = fakeStripeGateway();
    Notification::fake();
    $departure = bookingDeparture(['price_discount_percent' => 100]);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/bookings', bookingPayload($departure), [
        'Idempotency-Key' => Str::uuid()->toString(),
    ])->assertCreated()->assertJsonPath('data.attributes.status', 'confirmed')->assertJsonPath('data.attributes.total_amount', 0);
    expect($stripe->checkouts)->toBeEmpty()->and($departure->fresh()->available_spots)->toBe(9)
        ->and($departure->fresh()->reserved_spots)->toBe(1);
});

it('confirms paid checkout exactly once from a matching webhook', function () {
    fakeStripeGateway();
    Notification::fake();
    $user = User::factory()->create();
    $departure = bookingDeparture();
    $response = $this->actingAs($user)->postJson('/api/v1/bookings', bookingPayload($departure, 2), [
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);
    $booking = Booking::findOrFail($response->json('data.id'));
    $event = [
        'id' => 'evt_completed', 'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $booking->stripe_checkout_session_id, 'payment_status' => 'paid',
            'client_reference_id' => $booking->id,
            'amount_total' => $booking->total_amount, 'currency' => 'usd', 'payment_intent' => 'pi_test_1',
            'metadata' => ['booking_id' => $booking->id, 'booking_reference' => $booking->reference],
        ]],
    ];
    app(HandleStripeWebhook::class)->handle($event);
    app(HandleStripeWebhook::class)->handle($event);
    expect($booking->fresh()->status)->toBe(BookingStatus::CONFIRMED)
        ->and($booking->fresh()->stripe_payment_intent_id)->toBe('pi_test_1')
        ->and($departure->fresh()->available_spots)->toBe(8);
});

it('expires overdue holds and restores seats once', function () {
    $stripe = fakeStripeGateway();
    $user = User::factory()->create();
    $departure = bookingDeparture();
    $response = $this->actingAs($user)->postJson('/api/v1/bookings', bookingPayload($departure), [
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);
    Booking::findOrFail($response->json('data.id'))->update(['hold_expires_at' => now('UTC')->subMinute()]);
    $this->artisan('bookings:expire-holds')->assertSuccessful();
    $this->artisan('bookings:expire-holds')->assertSuccessful();
    expect(Booking::find($response->json('data.id'))->status)->toBe(BookingStatus::EXPIRED)
        ->and($departure->fresh()->available_spots)->toBe(10)
        ->and($departure->fresh()->reserved_spots)->toBe(0)
        ->and($stripe->expired)->toHaveCount(1);
});

it('cancels a paid booking after a successful refund and restores seats', function () {
    $stripe = fakeStripeGateway('succeeded');
    Notification::fake();
    $user = User::factory()->create();
    $departure = bookingDeparture();
    $booking = Booking::factory()->for($user)->create([
        'tour_id' => $departure->tour_id, 'tour_start_date_id' => $departure->id,
        'departure_datetime_utc' => $departure->start_datetime_utc, 'stripe_payment_intent_id' => 'pi_cancel_me',
    ]);
    $booking->travelers()->create(['full_name' => 'Traveler', 'email' => 't@example.com', 'phone' => '+12025550123']);
    $departure->forceFill(['available_spots' => 9, 'reserved_spots' => 1])->save();
    $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertOk()->assertJsonPath('data.attributes.status', 'cancelled');
    expect($departure->fresh()->available_spots)->toBe(10)->and($departure->fresh()->reserved_spots)->toBe(0)
        ->and($stripe->refunds)->toHaveCount(1);
});

it('finalizes a pending cancellation exactly once from a matching refund webhook', function () {
    fakeStripeGateway('pending');
    Notification::fake();
    $user = User::factory()->create();
    $departure = bookingDeparture();
    $booking = Booking::factory()->for($user)->create([
        'tour_id' => $departure->tour_id, 'tour_start_date_id' => $departure->id,
        'status' => BookingStatus::CANCELLATION_PENDING,
        'departure_datetime_utc' => $departure->start_datetime_utc,
        'stripe_payment_intent_id' => 'pi_refunded', 'stripe_refund_id' => 're_pending',
        'stripe_refund_status' => 'pending',
    ]);
    $departure->forceFill(['available_spots' => 9, 'reserved_spots' => 1])->save();
    $event = [
        'id' => 'evt_refund', 'type' => 'refund.updated',
        'data' => ['object' => [
            'id' => 're_pending', 'status' => 'succeeded', 'payment_intent' => 'pi_refunded',
            'amount' => $booking->total_amount, 'currency' => 'usd', 'metadata' => ['booking_id' => $booking->id],
        ]],
    ];
    app(HandleStripeWebhook::class)->handle($event);
    app(HandleStripeWebhook::class)->handle($event);
    expect($booking->fresh()->status)->toBe(BookingStatus::CANCELLED)
        ->and($departure->fresh()->available_spots)->toBe(10)
        ->and($departure->fresh()->reserved_spots)->toBe(0);
});

it('protects booking reads by ownership and enforces the cancellation cutoff', function () {
    fakeStripeGateway();
    $owner = User::factory()->create();
    $booking = Booking::factory()->for($owner)->create(['departure_datetime_utc' => now('UTC')->addHours(47)]);
    $this->actingAs(User::factory()->create())->getJson("/api/v1/bookings/{$booking->id}")->assertNotFound();
    $this->actingAs($owner)->postJson("/api/v1/bookings/{$booking->id}/cancel")
        ->assertUnprocessable()->assertJsonValidationErrors('booking');
});

it('rejects a departure whose parent tour was soft deleted', function () {
    fakeStripeGateway();
    $departure = bookingDeparture();
    $departure->tour->delete();

    $this->actingAs(User::factory()->create())->postJson('/api/v1/bookings', bookingPayload($departure), [
        'Idempotency-Key' => Str::uuid()->toString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('tour_start_date_id');
});

it('initializes checkout when a concurrent idempotent replay finds an unfinished booking', function () {
    $stripe = fakeStripeGateway();
    $user = User::factory()->create();
    $departure = bookingDeparture();
    $payload = bookingPayload($departure);
    $key = Str::uuid()->toString();
    $data = new BookingData($departure->id, $payload['travelers']);
    $booking = Booking::factory()->for($user)->create([
        'tour_id' => $departure->tour_id,
        'tour_start_date_id' => $departure->id,
        'status' => BookingStatus::PENDING_PAYMENT,
        'departure_datetime_utc' => $departure->start_datetime_utc,
        'idempotency_key_hash' => hash('sha256', $key),
        'request_hash' => $data->requestHash(),
        'stripe_checkout_session_id' => null,
        'stripe_checkout_url' => null,
        'hold_expires_at' => now('UTC')->addMinutes(30),
    ]);
    $booking->travelers()->createMany($payload['travelers']);

    $this->actingAs($user)->postJson('/api/v1/bookings', $payload, ['Idempotency-Key' => $key])
        ->assertOk()->assertJsonPath('data.attributes.status', 'pending_payment')
        ->assertJsonPath('data.attributes.checkout_url', 'https://checkout.stripe.test/'.$booking->id);
    expect($stripe->checkouts)->toHaveCount(1);
});

it('confirms a paid session found by hold reconciliation instead of releasing its seats', function () {
    $stripe = fakeStripeGateway();
    Notification::fake();
    $user = User::factory()->create();
    $departure = bookingDeparture();
    $response = $this->actingAs($user)->postJson('/api/v1/bookings', bookingPayload($departure), [
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);
    $booking = Booking::findOrFail($response->json('data.id'));
    $booking->update(['hold_expires_at' => now('UTC')->subMinute()]);
    $stripe->checkoutSessions[$booking->stripe_checkout_session_id]['status'] = 'complete';
    $stripe->checkoutSessions[$booking->stripe_checkout_session_id]['payment_status'] = 'paid';
    $stripe->checkoutSessions[$booking->stripe_checkout_session_id]['payment_intent'] = 'pi_reconciled';

    $this->artisan('bookings:expire-holds')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::CONFIRMED)
        ->and($booking->fresh()->stripe_payment_intent_id)->toBe('pi_reconciled')
        ->and($departure->fresh()->available_spots)->toBe(9)
        ->and($departure->fresh()->reserved_spots)->toBe(1);
});

it('ignores a delayed webhook from a superseded refund attempt', function () {
    fakeStripeGateway();
    $user = User::factory()->create();
    $departure = bookingDeparture();
    $booking = Booking::factory()->for($user)->create([
        'tour_id' => $departure->tour_id,
        'tour_start_date_id' => $departure->id,
        'status' => BookingStatus::CANCELLATION_PENDING,
        'departure_datetime_utc' => $departure->start_datetime_utc,
        'stripe_payment_intent_id' => 'pi_retry',
        'stripe_refund_id' => 're_current',
        'stripe_refund_status' => 'pending',
        'refund_attempt' => 2,
    ]);
    $event = [
        'id' => 'evt_old_refund', 'type' => 'refund.failed',
        'data' => ['object' => [
            'id' => 're_old', 'status' => 'failed', 'payment_intent' => 'pi_retry',
            'amount' => $booking->total_amount, 'currency' => 'usd',
            'metadata' => ['booking_id' => $booking->id], 'failure_reason' => 'declined',
        ]],
    ];

    app(HandleStripeWebhook::class)->handle($event);

    expect($booking->fresh()->status)->toBe(BookingStatus::CANCELLATION_PENDING)
        ->and($booking->fresh()->stripe_refund_id)->toBe('re_current');
});

it('rejects administrative capacity changes that exclude reserved bookings', function () {
    $departure = bookingDeparture();
    $departure->forceFill(['available_spots' => 8, 'reserved_spots' => 2])->save();
    authenticateTourAdmin();

    $this->patchJson("/api/v1/tours/{$departure->tour_id}/start-dates/{$departure->id}", [
        'available_spots' => 9,
    ])->assertUnprocessable()->assertJsonValidationErrors('available_spots');

    $this->patchJson("/api/v1/tours/{$departure->tour_id}", [
        'max_group_size' => 9,
    ])->assertUnprocessable()->assertJsonValidationErrors('max_group_size');
});
