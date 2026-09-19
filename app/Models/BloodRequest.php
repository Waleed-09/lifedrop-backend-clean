<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloodRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_id', 'blood_group', 'units', 'hospital',
        'latitude', 'longitude', 'urgency', 'status',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function matchedDonors()
    {
        return $this->belongsToMany(User::class, 'request_donor', 'blood_request_id', 'donor_id')
            ->withPivot(['status', 'responded_at'])
            ->withTimestamps();
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }
}
