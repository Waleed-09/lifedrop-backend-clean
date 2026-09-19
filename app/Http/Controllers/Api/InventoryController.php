<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(User $bank)
    {
        return response()->json($bank->inventory);
    }

    public function update(Request $request, User $bank)
    {
        abort_unless($request->user()->id === $bank->id, 403);

        $data = $request->validate([
            'blood_group' => ['required', 'in:O+,O-,A+,A-,B+,B-,AB+,AB-'],
            'units_available' => ['required', 'integer', 'min:0'],
        ]);

        $inventory = Inventory::updateOrCreate(
            ['blood_bank_id' => $bank->id, 'blood_group' => $data['blood_group']],
            ['units_available' => $data['units_available']]
        );

        return response()->json($inventory);
    }
}
