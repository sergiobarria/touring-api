<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\ResetUserPassword;
use App\Actions\Auth\SendEmailVerification;
use App\Actions\Auth\SendPasswordResetLink;
use App\Actions\Auth\UpdateUserPassword;
use App\Actions\Auth\VerifyUserEmail;
use App\Actions\Users\GetCurrentUser;
use App\DataTransferObjects\AuthenticationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\SendEmailVerificationRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Resources\ManagedUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Throwable;

#[Group('Authentication')]
class AuthController extends Controller
{
    /**
     * Register a user.
     *
     * Create an account and immediately issue an API token.
     *
     * @throws Throwable
     */
    #[Response(429, description: 'Too many registration attempts.', type: 'array{message: string}')]
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        return $this->authenticationResponse($registerUser->handle($request), 201);
    }

    /**
     * Log in.
     *
     * Exchange valid credentials for an API token.
     */
    #[Response(429, description: 'Too many login requests or failed login attempts.', type: 'array{message: string}')]
    public function login(LoginRequest $request, LoginUser $loginUser): JsonResponse
    {
        return $this->authenticationResponse($loginUser->handle($request));
    }

    /**
     * Log out.
     *
     * Revoke only the API token used for this request.
     */
    #[Response(401, description: 'Unauthenticated.', type: 'array{message: string}')]
    public function logout(Request $request, LogoutUser $logoutUser): HttpResponse
    {
        $logoutUser->handle($request);

        return response()->noContent();
    }

    public function forgotPassword(ForgotPasswordRequest $request, SendPasswordResetLink $action): JsonResponse
    {
        $action->handle($request);

        return response()->json(['message' => 'If an account exists, a password reset link has been sent.'], 202);
    }

    /**
     * @throws Throwable
     */
    public function resetPassword(ResetPasswordRequest $request, ResetUserPassword $action): HttpResponse
    {
        $action->handle($request);

        return response()->noContent();
    }

    /**
     * @throws Throwable
     */
    public function updatePassword(UpdatePasswordRequest $request, UpdateUserPassword $action): HttpResponse
    {
        $action->handle($request->user(), $request);

        return response()->noContent();
    }

    public function sendEmailVerification(SendEmailVerificationRequest $request, SendEmailVerification $action): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($user);

        return response()->noContent();
    }

    public function verifyEmail(string $user, string $hash, VerifyUserEmail $action): HttpResponse
    {
        $action->handle($user, $hash);

        return response()->noContent();
    }

    public function me(Request $request, GetCurrentUser $action): ManagedUserResource
    {
        /** @var User $user */
        $user = $request->user();

        return ManagedUserResource::make($action->handle($user));
    }

    private function authenticationResponse(AuthenticationResult $result, int $status = 200): JsonResponse
    {
        return UserResource::make($result->user)
            ->additional([
                'meta' => [
                    'access_token' => $result->token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $result->token->accessToken->expires_at->toIso8601String(),
                ],
            ])
            ->response()
            ->setStatusCode($status);
    }
}
