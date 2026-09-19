<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_id', 'blood_request_id', 'blood_bank_id', 'units', 'date', 'status',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function donor()
    {
        return $this->belongsTo(User::class, 'donor_id');
    }

    public function bloodRequest()
    {
        return $this->belongsTo(BloodRequest::class);
    }

    public function bloodBank()
    {
        return $this->belongsTo(User::class, 'blood_bank_id');
    }
}
