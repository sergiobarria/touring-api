<?php

namespace App\Services\Auditing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use Throwable;

final readonly class AuditService
{
    /**
     * Record an auditable relationship or domain-state change.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     *
     * @throws Throwable
     */
    public function record(Model $model, array $oldValues, array $newValues, string $tags): void
    {
        if (! config('audit.enabled', true)
            || (app()->runningInConsole() && ! config('audit.console', false))) {
            return;
        }

        $user = Auth::user();
        $request = app()->bound('request') ? app(Request::class) : null;

        Audit::query()->create([
            'user_type' => $user?->getMorphClass(),
            'user_id' => $user?->getAuthIdentifier(),
            'event' => 'updated',
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => ($userAgent = $request?->userAgent()) !== null
                ? Str::limit($userAgent, 1023, '')
                : null,
            'tags' => $tags,
        ]);
    }
}
