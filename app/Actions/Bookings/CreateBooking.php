<?php

namespace App\Actions\Bookings;

use App\DataTransferObjects\BookingData;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourStartDate;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Services\Bookings\BookingService;
use App\Services\Payments\StripeGateway;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final readonly class CreateBooking
{
    public function __construct(private BookingService $bookings, private StripeGateway $stripe) {}

    /** @throws Throwable */
    public function handle(User $user, BookingData $data, string $idempotencyKey): Booking
    {
        $keyHash = hash('sha256', $idempotencyKey);
        $requestHash = $data->requestHash();
        $existing = Booking::query()->whereBelongsTo($user)->where('idempotency_key_hash', $keyHash)->first();
        if ($existing !== null) {
            return $this->replayOrInitialize($existing, $requestHash);
        }

        try {
            $booking = DB::transaction(function () use ($user, $data, $keyHash, $requestHash): Booking {
                $startDate = TourStartDate::query()->with('tour')->lockForUpdate()->findOrFail($data->tourStartDateId);
                $tour = $startDate->tour;
                $quantity = count($data->travelers);
                if (! $tour instanceof Tour || ! $tour->is_active || ! $startDate->is_active || $startDate->start_datetime_utc->lte(now('UTC'))) {
                    throw ValidationException::withMessages(['tour_start_date_id' => 'The selected departure is not available for booking.']);
                }
                if ($startDate->available_spots < $quantity) {
                    throw ValidationException::withMessages(['travelers' => 'The selected departure does not have enough available spots.']);
                }
                $unitAmount = $this->bookings->unitAmount((string) $tour->price, $tour->price_discount_percent);
                $free = $unitAmount === 0;
                $booking = Booking::query()->create([
                    'user_id' => $user->id, 'tour_id' => $tour->id, 'tour_start_date_id' => $startDate->id,
                    'reference' => 'BK-'.Str::upper(Str::random(12)),
                    'status' => $free ? BookingStatus::CONFIRMED : BookingStatus::PENDING_PAYMENT,
                    'ticket_quantity' => $quantity, 'unit_amount' => $unitAmount, 'total_amount' => $unitAmount * $quantity,
                    'currency' => strtolower((string) config('services.stripe.currency')), 'tour_name' => $tour->name,
                    'departure_datetime_utc' => $startDate->start_datetime_utc, 'purchaser_name' => $user->name,
                    'purchaser_email' => $user->email, 'discount_percent' => $tour->price_discount_percent,
                    'idempotency_key_hash' => $keyHash, 'request_hash' => $requestHash,
                    'hold_expires_at' => $free ? null : now('UTC')->addMinutes((int) config('services.stripe.checkout_hold_minutes')),
                    'paid_at' => $free ? now('UTC') : null,
                ]);
                $booking->travelers()->createMany($data->travelers);
                $startDate->decrement('available_spots', $quantity);
                $startDate->increment('reserved_spots', $quantity);
                if ($free) {
                    $user->notify(new BookingConfirmedNotification($booking));
                }

                return $booking;
            });
        } catch (QueryException $exception) {
            $booking = Booking::query()->whereBelongsTo($user)->where('idempotency_key_hash', $keyHash)->first();
            if ($booking === null) {
                throw $exception;
            }

            return $this->replayOrInitialize($booking, $requestHash);
        }

        if ($booking->status === BookingStatus::CONFIRMED) {
            return $booking->load('travelers');
        }

        return $this->initializeCheckout($booking);
    }

    private function initializeCheckout(Booking $booking): Booking
    {
        try {
            $session = $this->stripe->createCheckout($booking);
            $booking->update([
                'stripe_checkout_session_id' => $session->id, 'stripe_checkout_url' => $session->url,
                'hold_expires_at' => now('UTC')->setTimestamp($session->expiresAt),
            ]);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($booking): void {
                $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                $this->bookings->expire($booking);
                $booking->update(['failure_code' => 'checkout_creation_failed']);
            });
            throw $exception;
        }

        return $booking->load('travelers');
    }

    private function replayOrInitialize(Booking $booking, string $requestHash): Booking
    {
        if (! hash_equals($booking->request_hash, $requestHash)) {
            throw new ConflictHttpException('The Idempotency-Key was already used with different booking details.');
        }

        if ($booking->status === BookingStatus::PENDING_PAYMENT && $booking->stripe_checkout_session_id === null) {
            return $this->initializeCheckout($booking);
        }

        return $booking->load('travelers');
    }
}
