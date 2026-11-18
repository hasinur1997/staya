<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $name_bn
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Member|null $member
 * @property-read string $display_name
 */
#[Fillable(['name', 'name_bn', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Emails to a user (password reset, verification) go out in the default
     * language the Admin set, as people have no language of their own saved.
     */
    public function preferredLocale(): string
    {
        return MessSetting::defaultLocale();
    }

    /**
     * Defaults matching the columns, so a model fresh from create() is
     * already active.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

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
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The name in the language on screen: Bangla when the app is in Bangla
     * and a Bangla name is set, otherwise the English name.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => app()->getLocale() === 'bn' && $this->name_bn !== '' ? $this->name_bn : $this->name);
    }

    /**
     * Users who may sign in.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The member profile; staff-only accounts have none.
     *
     * @return HasOne<Member, $this>
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * Whether the user's member profile is currently active. Inactive members
     * may sign in and read, but not create purchases or deposits.
     */
    public function isActiveMember(): bool
    {
        return $this->member?->status === MemberStatus::Active;
    }

    /**
     * Whether the user is an applicant (pending or rejected) with no access.
     */
    public function isApplicant(): bool
    {
        return $this->member?->status->isApplicant() ?? false;
    }

    /**
     * Whether the user holds every one of the given permission names.
     *
     * @param  iterable<string>  $permissions
     */
    public function holdsAllPermissions(iterable $permissions): bool
    {
        $held = $this->getAllPermissions()->pluck('name');

        return collect($permissions)->diff($held)->isEmpty();
    }

    /**
     * The fields recorded as before/after values in the audit log.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $this->roles()->pluck('name')->sort()->values()->all(),
        ];
    }
}
