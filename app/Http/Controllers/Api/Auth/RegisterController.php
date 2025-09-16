<?php

namespace App\Http\Controllers\Api\Auth;

use App\Data\RegisterData;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

#[Group("Authentication")]
class RegisterController extends Controller
{
    /** Register */
    public function __invoke(RegisterData $data): JsonResponse
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'data' => UserData::from($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], Response::HTTP_CREATED);
    }
}
