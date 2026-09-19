<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBloodRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated user can request blood
    }

    public function rules(): array
    {
        return [
            'blood_group' => ['required', 'in:O+,O-,A+,A-,B+,B-,AB+,AB-'],
            'units' => ['required', 'integer', 'min:1', 'max:20'],
            'hospital' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'urgency' => ['required', 'in:normal,urgent,critical'],
        ];
    }
}
