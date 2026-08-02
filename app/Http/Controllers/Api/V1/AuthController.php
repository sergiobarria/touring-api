<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\RegisterUser;
use App\DataTransferObjects\AuthenticationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

#[Group('Authentication')]
class AuthController extends Controller
{
    /**
     * Register a user.
     *
     * Create an account and immediately issue an API token.
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
    #[Response(429, description: 'Too many failed login attempts.', type: 'array{message: string}')]
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
