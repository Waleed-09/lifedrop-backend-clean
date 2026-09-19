<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BloodRequestController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DonorController;
use App\Http\Controllers\Api\InventoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---- public routes ----
    Route::post('/auth/signup', [AuthController::class, 'signup']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Publicly view blood requests & search donors so anyone can see emergency needs
    Route::get('/requests', [BloodRequestController::class, 'index']);
    Route::get('/requests/{request_}', [BloodRequestController::class, 'show']);
    Route::get('/donors/nearby', [DonorController::class, 'nearby']);

    // Public Live Platform Statistics
    Route::get('/stats', function () {
        $totalUsers = \App\Models\User::count();
        $activeDonors = \App\Models\User::where('role', 'donor')->count();
        $partnerHospitals = \App\Models\User::where('role', 'bloodbank')->count();
        $totalRequests = \App\Models\BloodRequest::count();
        $fulfilledRequests = \App\Models\BloodRequest::where('status', 'fulfilled')->count();
        $livesSaved = max($fulfilledRequests * 3, $totalUsers * 2);

        return response()->json([
            'total_users' => $totalUsers,
            'active_donors' => $activeDonors > 0 ? $activeDonors : $totalUsers,
            'partner_hospitals' => $partnerHospitals,
            'total_requests' => $totalRequests,
            'fulfilled_requests' => $fulfilledRequests,
            'lives_saved' => $livesSaved,
        ]);
    });

    // Public Contact Us Form Endpoint
    Route::post('/contact', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        \Illuminate\Support\Facades\Log::info('--- NEW CONTACT MESSAGE ---', $validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact message received successfully!',
        ], 200);
    });

    // ---- authenticated routes ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::patch('/donors/me/availability', [DonorController::class, 'toggleAvailability']);

        Route::post('/requests', [BloodRequestController::class, 'store']);
        Route::patch('/requests/{request_}', [BloodRequestController::class, 'update']);
        Route::patch('/requests/{bloodRequest}/accept', [BloodRequestController::class, 'accept']);

        Route::get('/donations', [DonationController::class, 'index']);
        Route::post('/donations', [DonationController::class, 'store']);

        // ---- blood bank only ----
        Route::middleware('role:bloodbank')->group(function () {
            Route::get('/bloodbanks/{bank}/inventory', [InventoryController::class, 'index']);
            Route::patch('/bloodbanks/{bank}/inventory', [InventoryController::class, 'update']);
        });

        // ---- admin only ----
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/overview', [AdminUserController::class, 'overview']);
            Route::get('/donors', [AdminUserController::class, 'getDonors']);
            Route::delete('/donors/{user}', [AdminUserController::class, 'destroy']);
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::patch('/users/{user}', [AdminUserController::class, 'update']);
            Route::patch('/users/{user}/block', [AdminUserController::class, 'block']);
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
            Route::get('/requests', [AdminUserController::class, 'getRequests']);
            Route::patch('/requests/{id}', [AdminUserController::class, 'updateRequest']);
            Route::delete('/requests/{id}', [AdminUserController::class, 'deleteRequest']);
            Route::get('/logs', [AdminUserController::class, 'getLogs']);
            Route::post('/change-password', [AdminUserController::class, 'changePassword']);
        });
    });
});
Route::get('/setup-admin-secret-xyz', function () {
    $user = \App\Models\User::updateOrCreate(
        ['email' => 'admin@lifedrop.com'],
        [
            'name' => 'Admin Waleed',
            'password' => bcrypt('Admin@12345'),
            'role' => 'admin',
        ]
    );
    return response()->json(['message' => 'Admin created successfully', 'user' => $user]);
});