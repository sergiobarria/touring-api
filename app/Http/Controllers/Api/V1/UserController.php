<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\ListUsers;
use App\Actions\Users\ShowUser;
use App\Actions\Users\UpdateUserRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRoleRequest;
use App\Http\Requests\Api\V1\UserListRequest;
use App\Http\Resources\ManagedUserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Throwable;

#[Group('Users')]
final class UserController extends Controller
{
    public function index(UserListRequest $request, ListUsers $action): AnonymousResourceCollection
    {
        return ManagedUserResource::collection($action->handle(
            perPage: $request->integer('per_page', 15),
            page: $request->integer('page', 1),
        ));
    }

    /**
     * @throws Throwable
     */
    public function store(StoreUserRequest $request, CreateUser $action): JsonResponse
    {
        return ManagedUserResource::make($action->handle($request))->response()->setStatusCode(201);
    }

    public function show(string $user, ShowUser $action): ManagedUserResource
    {
        return ManagedUserResource::make($action->handle($user));
    }

    /**
     * @throws Throwable
     */
    public function updateRole(UpdateUserRoleRequest $request, string $user, UpdateUserRole $action): ManagedUserResource
    {
        return ManagedUserResource::make($action->handle($user, UserRole::from((string) $request->string('role'))));
    }

    /**
     * @throws Throwable
     */
    public function destroy(string $user, DeleteUser $action): Response
    {
        $action->handle($user);

        return response()->noContent();
    }
}
