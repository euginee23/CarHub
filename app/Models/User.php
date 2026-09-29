<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\DocumentStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
 * @property bool $is_admin
 * @property Carbon|null $owner_verified_at
 * @property Carbon|null $suspended_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Vehicle> $vehicles
 * @property-read Collection<int, OwnerApplication> $ownerApplications
 * @property-read OwnerApplication|null $latestOwnerApplication
 * @property-read Collection<int, Booking> $bookings
 * @property-read Collection<int, Booking> $ownerBookings
 * @property-read Collection<int, VerificationDocument> $identityDocuments
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
        'is_admin' => false,
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
            'is_admin' => 'boolean',
            'owner_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * The vehicles this user lists as an owner.
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'owner_id');
    }

    /**
     * Every owner application this user has submitted.
     *
     * @return HasMany<OwnerApplication, $this>
     */
    public function ownerApplications(): HasMany
    {
        return $this->hasMany(OwnerApplication::class);
    }

    /**
     * The user's most recent owner application.
     *
     * @return HasOne<OwnerApplication, $this>
     */
    public function latestOwnerApplication(): HasOne
    {
        return $this->hasOne(OwnerApplication::class)->latestOfMany();
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
    public function ownerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'owner_id');
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
     * Determine whether the user has been verified to list vehicles.
     */
    public function isVerifiedOwner(): bool
    {
        return $this->owner_verified_at !== null;
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
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
