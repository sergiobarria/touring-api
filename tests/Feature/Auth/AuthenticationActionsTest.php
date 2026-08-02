<?php

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\RegisterUser;
use App\DataTransferObjects\AuthenticationResult;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Users\UserRoleService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

readonly class FailingAuthenticationTokenService extends AccessTokenService
{
    public function issue(User $user): NewAccessToken
    {
        throw new RuntimeException('Token issuance failed.');
    }
}

function validatedAuthRequest(FormRequest $request): FormRequest
{
    $request->setContainer(app());
    $request->validateResolved();

    return $request;
}

function registerActionRequest(): RegisterRequest
{
    /** @var RegisterRequest $request */
    $request = validatedAuthRequest(RegisterRequest::create('/api/v1/auth/register', 'POST', [
        'name' => 'Jane Doe',
        'email' => ' JANE@EXAMPLE.COM ',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]));

    return $request;
}

function loginActionRequest(string $password = 'correct-password'): LoginRequest
{
    /** @var LoginRequest $request */
    $request = validatedAuthRequest(LoginRequest::create('/api/v1/auth/login', 'POST', [
        'email' => ' JANE@EXAMPLE.COM ',
        'password' => $password,
    ], server: ['REMOTE_ADDR' => '192.0.2.1']));

    return $request;
}

it('registers a user and returns the issued authentication result atomically', function () {
    $this->freezeTime();

    $result = app(RegisterUser::class)->handle(registerActionRequest());

    expect($result)->toBeInstanceOf(AuthenticationResult::class)
        ->and($result->user->email)->toBe('jane@example.com')
        ->and(Hash::check('correct-horse-battery-staple', $result->user->password))->toBeTrue()
        ->and($result->token->accessToken->tokenable_id)->toBe($result->user->id)
        ->and($result->token->accessToken->name)->toBe('auth-token')
        ->and($result->user->hasExactRoles('user'))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});

it('rolls registration back when token issuance fails', function () {
    expect(fn () => (new RegisterUser(
        new FailingAuthenticationTokenService,
        app(UserRoleService::class),
    ))->handle(registerActionRequest()))
        ->toThrow(RuntimeException::class, 'Token issuance failed.');

    expect(User::query()->count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});

it('logs in through the action and rehashes the password when needed', function () {
    $oldHash = Hash::make('correct-password', ['rounds' => 5]);
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'correct-password',
    ]);
    DB::table('users')->where('id', $user->id)->update(['password' => $oldHash]);

    $result = app(LoginUser::class)->handle(loginActionRequest());

    expect($result)->toBeInstanceOf(AuthenticationResult::class)
        ->and($result->user->is($user))->toBeTrue()
        ->and($result->user->password)->not->toBe($oldHash)
        ->and(Hash::needsRehash($result->user->password))->toBeFalse()
        ->and($result->token->accessToken->tokenable_id)->toBe($user->id);
});

it('records invalid login attempts and dispatches a lockout event', function () {
    Sleep::fake();

    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'correct-password',
    ]);

    $action = app(LoginUser::class);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        expect(fn () => $action->handle(loginActionRequest('incorrect-password')))
            ->toThrow(ValidationException::class);
    }

    Event::fake([Lockout::class]);

    expect(fn () => $action->handle(loginActionRequest('incorrect-password')))
        ->toThrow(ThrottleRequestsException::class);

    Event::assertDispatched(Lockout::class);
    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('issues fixed 30 day wildcard tokens through the token service', function () {
    $this->freezeTime();

    $token = app(AccessTokenService::class)->issue(User::factory()->create());

    expect($token->accessToken->name)->toBe('auth-token')
        ->and($token->accessToken->abilities)->toBe(['*'])
        ->and($token->accessToken->expires_at->isSameSecond(now()->addDays(30)))->toBeTrue();
});

it('revokes only the current token through the logout action', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('auth-token', ['*'], now()->addDays(30));
    $otherToken = $user->createToken('auth-token', ['*'], now()->addDays(30));
    $user->withAccessToken($currentToken->accessToken);

    $request = Request::create('/api/v1/auth/logout', 'POST');
    $request->setUserResolver(fn (): User => $user);

    app(LogoutUser::class)->handle($request);

    expect(PersonalAccessToken::findToken($currentToken->plainTextToken))->toBeNull()
        ->and(PersonalAccessToken::findToken($otherToken->plainTextToken))->not->toBeNull();
});
