<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements Auditable, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUlids, Notifiable, \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected array $auditExclude = [
        'password',
        'remember_token',
    ];

    /**
     * Replace excluded password data with a non-sensitive change marker.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAudit(array $data): array
    {
        if ($this->getAuditEvent() === 'updated' && $this->wasChanged('password')) {
            $data['new_values']['password_changed'] = true;
        }

        return $data;
    }

    public function leadTours(): HasMany
    {
        return $this->hasMany(Tour::class, 'lead_guide_id');
    }

    public function supportingTours(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class, 'guide_tour');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verified_at' => $this->freshTimestamp()])->save();
    }

    public function markEmailAsUnverified(): bool
    {
        return $this->forceFill(['email_verified_at' => null])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] mixed $token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
