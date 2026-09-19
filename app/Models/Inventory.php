<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = ['blood_bank_id', 'blood_group', 'units_available'];

    public function bloodBank()
    {
        return $this->belongsTo(User::class, 'blood_bank_id');
    }
}
