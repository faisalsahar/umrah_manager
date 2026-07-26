<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $onboarding_code
 * @property CarbonInterface|null $onboarding_notified_at
 * @property string|null $avatar
 * @property CarbonInterface|null $email_verified_at
 * @property string|null $password
 * @property string|null $provider
 * @property string|null $provider_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonInterface|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_organization_id
 * @property string|null $locale
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'provider', 'provider_id', 'avatar'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Give every new user a unique onboarding code.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->onboarding_code)) {
                $user->onboarding_code = static::generateOnboardingCode();
            }
        });
    }

    /**
     * Generate a short, unique, human-friendly onboarding code (e.g. "K7P2QX9M").
     */
    public static function generateOnboardingCode(): string
    {
        do {
            // Avoid ambiguous characters (0/O, 1/I) so it's easy to read out loud.
            $code = collect(str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'))
                ->random(8)
                ->implode('');
        } while (static::where('onboarding_code', $code)->exists());

        return $code;
    }

    /**
     * Ensure the user has an onboarding code, generating one if missing.
     */
    public function ensureOnboardingCode(): string
    {
        if (blank($this->onboarding_code)) {
            $this->forceFill(['onboarding_code' => static::generateOnboardingCode()])->save();
        }

        return $this->onboarding_code;
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
            'onboarding_notified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Is this user the application administrator?
     */
    public function isAdmin(): bool
    {
        $adminEmail = config('app.admin_email');

        return filled($adminEmail) && strcasecmp($this->email, $adminEmail) === 0;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Every organization this user belongs to, with their role on the pivot.
     *
     * @return BelongsToMany<Organization, $this, OrganizationMembership, 'pivot'>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationMembership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The organization the user is currently acting as — the context every screen
     * scopes to. The same shape as `current_team_id` in a teams-based app.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * This user's role within the given organization, or null if they are not a member.
     */
    public function roleIn(Organization $organization): ?OrganizationRole
    {
        $role = $this->organizations()->where('organization_id', $organization->id)->first()?->pivot->role;

        return $role ? OrganizationRole::from($role) : null;
    }

    /**
     * Whether this user belongs to the given organization at all.
     */
    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->roleIn($organization) !== null;
    }

    /**
     * Ensure the user always has a current organization to act as, defaulting to
     * their first membership if none is set (or the existing one is no longer valid).
     */
    public function ensureCurrentOrganization(): ?Organization
    {
        $current = $this->current_organization_id ? $this->currentOrganization : null;

        if ($current && $this->belongsToOrganization($current)) {
            return $current;
        }

        $first = $this->organizations()->first();

        $this->forceFill(['current_organization_id' => $first?->id])->save();

        return $first;
    }
}
