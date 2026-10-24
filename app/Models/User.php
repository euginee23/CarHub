<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\DocumentStatus;
use App\Enums\IdentityStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property Carbon|null $birthdate
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property Carbon|null $company_verified_at
 * @property string|null $company_name
 * @property string|null $company_address
 * @property Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Vehicle> $vehicles
 * @property-read Collection<int, CompanyApplication> $companyApplications
 * @property-read CompanyApplication|null $latestCompanyApplication
 * @property-read Collection<int, Booking> $bookings
 * @property-read Collection<int, Booking> $companyBookings
 * @property-read Collection<int, VerificationDocument> $identityDocuments
 * @property-read RenterPreference|null $renterPreference
 */
#[Fillable(['name', 'email', 'phone', 'address', 'birthdate', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * How many different government IDs a renter must have approved.
     */
    public const int REQUIRED_IDENTITY_DOCUMENTS = 2;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'renter',
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
            'birthdate' => 'date',
            'password' => 'hashed',
            'role' => UserRole::class,
            'company_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * The vehicles this user lists as a rental company.
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'company_id');
    }

    /**
     * Every rental company application this user has submitted.
     *
     * @return HasMany<CompanyApplication, $this>
     */
    public function companyApplications(): HasMany
    {
        return $this->hasMany(CompanyApplication::class);
    }

    /**
     * The user's most recent rental company application.
     *
     * @return HasOne<CompanyApplication, $this>
     */
    public function latestCompanyApplication(): HasOne
    {
        return $this->hasOne(CompanyApplication::class)->latestOfMany();
    }

    /**
     * The bookings this user has made as a renter.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'renter_id');
    }

    /**
     * The bookings made on this user's vehicles.
     *
     * @return HasMany<Booking, $this>
     */
    public function companyBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'company_id');
    }

    /**
     * What this renter has said they look for in a vehicle.
     *
     * @return HasOne<RenterPreference, $this>
     */
    public function renterPreference(): HasOne
    {
        return $this->hasOne(RenterPreference::class);
    }

    /**
     * The government IDs this user has submitted to verify their identity as a renter.
     *
     * @return MorphMany<VerificationDocument, $this>
     */
    public function identityDocuments(): MorphMany
    {
        return $this->morphMany(VerificationDocument::class, 'documentable');
    }

    /**
     * Whether an administrator has approved at least two different kinds of
     * government ID for this user — the two-valid-ID requirement for renting.
     */
    public function hasVerifiedIdentity(): bool
    {
        return $this->identityDocuments()
            ->where('status', DocumentStatus::Approved)
            ->distinct()
            ->count('type') >= self::REQUIRED_IDENTITY_DOCUMENTS;
    }

    /**
     * Where the renter stands on the two-valid-ID requirement: approved once two
     * different IDs are approved, pending while two are on file awaiting review,
     * and not uploaded while fewer than two are on file (a rejected ID must be
     * replaced).
     */
    public function identityStatus(): IdentityStatus
    {
        return IdentityStatus::fromCounts(
            approved: $this->identityDocuments()->where('status', DocumentStatus::Approved)->distinct()->count('type'),
            onFile: $this->identityDocuments()->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Approved])->distinct()->count('type'),
        );
    }

    /**
     * Only renters at the given stage of ID verification.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithIdentityStatus(Builder $query, IdentityStatus $status): void
    {
        $required = self::REQUIRED_IDENTITY_DOCUMENTS;
        $approved = fn (Builder $documents) => $documents->where('status', DocumentStatus::Approved);
        $onFile = fn (Builder $documents) => $documents->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Approved]);

        $query->where('role', UserRole::Renter);

        match ($status) {
            IdentityStatus::Approved => $query->whereHas('identityDocuments', $approved, '>=', $required),
            IdentityStatus::Pending => $query->whereHas('identityDocuments', $onFile, '>=', $required)
                ->whereHas('identityDocuments', $approved, '<', $required),
            IdentityStatus::NotUploaded => $query->whereHas('identityDocuments', $onFile, '<', $required),
        };
    }

    /**
     * Whether this is an administrator account, which runs the platform.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Whether this is a rental company account, which lists vehicles.
     */
    public function isCompany(): bool
    {
        return $this->role === UserRole::Company;
    }

    /**
     * Whether this is a renter account, which books vehicles.
     */
    public function isRenter(): bool
    {
        return $this->role === UserRole::Renter;
    }

    /**
     * Determine whether the user is a rental company who has been verified to list vehicles.
     */
    public function isVerifiedCompany(): bool
    {
        return $this->isCompany() && $this->company_verified_at !== null;
    }

    /**
     * The name shown to other people: a verified rental company's business name,
     * or the account holder's own name.
     */
    public function displayName(): string
    {
        return $this->company_name ?: $this->name;
    }

    /**
     * Determine whether the user's account has been suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->displayName(), true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
