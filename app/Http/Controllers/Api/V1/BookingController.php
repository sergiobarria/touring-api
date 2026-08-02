<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\ListBookings;
use App\Actions\Bookings\ShowBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

#[Group('Bookings')]
final class BookingController extends Controller
{
    public function index(Request $request, ListBookings $action): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        /** @var User $user */ $user = $request->user();

        return BookingResource::collection($action->handle($user, $request->integer('per_page', 15), $request->integer('page', 1)));
    }

    /** @throws Throwable */
    public function store(StoreBookingRequest $request, CreateBooking $action): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $booking = $action->handle($user, $request->toDto(), $request->idempotencyKey());
        $status = $booking->wasRecentlyCreated ? 201 : 200;

        return BookingResource::make($booking)->response()->setStatusCode($status)
            ->header('Location', route('v1.bookings.show', $booking));
    }

    public function show(Request $request, string $booking, ShowBooking $action): BookingResource
    {
        /** @var User $user */ $user = $request->user();

        return BookingResource::make($action->handle($user, $booking));
    }

    /** @throws Throwable */
    public function cancel(Request $request, string $booking, CancelBooking $action): BookingResource
    {
        /** @var User $user */ $user = $request->user();

        return BookingResource::make($action->handle($user, $booking));
    }
}
