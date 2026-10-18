<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'blood_group',
        'latitude', 'longitude', 'address', 'city', 'district',
        'availability', 'is_available', 'is_verified',
        'last_donation_date', 'last_donated_at', 'donation_count', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['is_eligible', 'days_until_eligible'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_donation_date' => 'date',
            'last_donated_at' => 'datetime',
            'availability' => 'boolean',
            'is_available' => 'boolean',
            'is_verified' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ---- relationships ----

    public function bloodRequests()
    {
        return $this->hasMany(BloodRequest::class, 'requester_id');
    }

    public function donations()
    {
        return $this->hasMany(Donation::class, 'donor_id');
    }

    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'blood_bank_id');
    }

    public function matchedRequests()
    {
        return $this->belongsToMany(BloodRequest::class, 'request_donor', 'donor_id', 'blood_request_id')
            ->withPivot(['status', 'responded_at'])
            ->withTimestamps();
    }

    // ---- scopes ----

    public function scopeDonors($query)
    {
        return $query->where('role', 'donor');
    }

    public function scopeAvailable($query)
    {
        return $query->where(function ($q) {
            $q->where('availability', true)
              ->orWhere('is_available', true);
        });
    }

    public function scopeEligible($query)
    {
        $ninetyDaysAgo = now()->subDays(90);

        return $query->where(function ($q) use ($ninetyDaysAgo) {
            $q->where(function ($sub) {
                $sub->whereNull('last_donated_at')
                    ->whereNull('last_donation_date');
            })->orWhere(function ($sub) use ($ninetyDaysAgo) {
                $sub->whereNotNull('last_donated_at')
                    ->where('last_donated_at', '<=', $ninetyDaysAgo);
            })->orWhere(function ($sub) use ($ninetyDaysAgo) {
                $sub->whereNull('last_donated_at')
                    ->whereNotNull('last_donation_date')
                    ->where('last_donation_date', '<=', $ninetyDaysAgo);
            });
        });
    }

    public function getIsEligibleAttribute(): bool
    {
        $date = $this->last_donated_at ?? $this->last_donation_date;
        if (! $date) {
            return true;
        }

        return \Carbon\Carbon::parse($date)->addDays(90)->isPast();
    }

    public function getDaysUntilEligibleAttribute(): int
    {
        $date = $this->last_donated_at ?? $this->last_donation_date;
        if (! $date) {
            return 0;
        }

        $nextEligible = \Carbon\Carbon::parse($date)->addDays(90);
        if ($nextEligible->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($nextEligible, false) / 86400);
    }

    // blood-group compatibility: which donor groups can give to $recipientGroup
    public static function compatibleDonorGroups(string $recipientGroup): array
    {
        return match ($recipientGroup) {
            'O+'  => ['O+', 'O-'],
            'O-'  => ['O-'],
            'A+'  => ['A+', 'A-', 'O+', 'O-'],
            'A-'  => ['A-', 'O-'],
            'B+'  => ['B+', 'B-', 'O+', 'O-'],
            'B-'  => ['B-', 'O-'],
            'AB+' => ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'], // universal recipient
            'AB-' => ['O-', 'A-', 'B-', 'AB-'],
            default => [],
        };
    }
}
