<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bookings\HandleStripeWebhook;
use App\Http\Controllers\Controller;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeGateway $stripe, HandleStripeWebhook $action): JsonResponse
    {
        try {
            $event = $stripe->constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        $action->handle($event);

        return response()->json(['received' => true]);
    }
}
