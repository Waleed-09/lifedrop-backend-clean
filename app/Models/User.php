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
        'latitude', 'longitude', 'address', 'availability',
        'last_donation_date', 'donation_count', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_donation_date' => 'date',
            'availability' => 'boolean',
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
        return $query->where('availability', true);
    }

    public function scopeEligible($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_donation_date')
              ->orWhere('last_donation_date', '<=', now()->subWeeks(12));
        });
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
