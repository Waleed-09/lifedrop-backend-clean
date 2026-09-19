<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BloodRequest;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminUserController extends Controller
{
    /**
     * Admin Overview Statistics & Analytics
     */
    public function overview()
    {
        $totalUsers = User::count();
        $totalDonors = User::where('role', 'donor')->count();
        $totalPatients = User::where('role', 'recipient')->count();

        // Check availability column safely
        $activeDonors = User::where('role', 'donor')->where(function ($q) {
            if (Schema::hasColumn('users', 'availability')) {
                $q->where('availability', true);
            }
            if (Schema::hasColumn('users', 'is_available')) {
                $q->orWhere('is_available', true);
            }
        })->count();
        
        $totalRequests = BloodRequest::count();
        $pendingRequests = BloodRequest::whereIn('status', ['pending', 'open'])->count();
        $fulfilledRequests = BloodRequest::where('status', 'fulfilled')->count();
        $emergencyRequests = BloodRequest::where('urgency', 'critical')->count();
        $cancelledRequests = BloodRequest::where('status', 'cancelled')->count();

        // Blood Group Distribution Matrix
        $groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
        $bloodGroupStats = collect($groups)->map(function ($bg) {
            $donors = User::where('role', 'donor')->where('blood_group', $bg)->count();
            $requests = BloodRequest::where('blood_group', $bg)->count();
            $fulfilled = BloodRequest::where('blood_group', $bg)->where('status', 'fulfilled')->count();

            return [
                'blood_group' => $bg,
                'donors' => $donors,
                'requests' => $requests,
                'fulfilled' => $fulfilled,
            ];
        });

        // Dynamic Analytics Timeline (last 4 months)
        $analyticsTimeline = collect([3, 2, 1, 0])->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $monthLabel = $date->format('M');

            $usersCount = User::where('created_at', '<=', $date->endOfMonth())->count();
            $donorsCount = User::where('role', 'donor')->where('created_at', '<=', $date->endOfMonth())->count();
            $requestsCount = BloodRequest::where('created_at', '<=', $date->endOfMonth())->count();
            $emergencyCount = BloodRequest::where('urgency', 'critical')->where('created_at', '<=', $date->endOfMonth())->count();

            return [
                'month' => $monthLabel,
                'users' => $usersCount,
                'donors' => $donorsCount,
                'requests' => $requestsCount,
                'emergency' => $emergencyCount,
            ];
        });

        // Recent Audit Logs
        $recentLogs = AdminActivityLog::latest()->take(10)->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'admin_name' => $log->admin_name,
                'admin' => $log->admin_name,
                'action' => $log->action,
                'description' => $log->description,
                'target' => $log->description,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String() ?? (string) $log->created_at,
                'time' => $log->created_at?->diffForHumans() ?? 'Recently',
            ];
        });

        return response()->json([
            'total_users' => $totalUsers,
            'total_donors' => $totalDonors,
            'total_patients' => $totalPatients,
            'active_donors' => $activeDonors,
            'total_requests' => $totalRequests,
            'pending_requests' => $pendingRequests,
            'fulfilled_requests' => $fulfilledRequests,
            'emergency_requests' => $emergencyRequests,
            'cancelled_requests' => $cancelledRequests,
            'blood_group_stats' => $bloodGroupStats,
            'analytics_timeline' => $analyticsTimeline,
            'recent_activities' => $recentLogs,
        ]);
    }

    /**
     * Users & Patients Management
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = strtolower(trim($request->search));
            $query->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$s}%"])
                  ->orWhere('phone', 'LIKE', "%{$s}%")
                  ->orWhereRaw('LOWER(address) LIKE ?', ["%{$s}%"]);
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'data' => $paginated->items(),
        ]);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'role' => ['sometimes', 'in:donor,recipient,bloodbank,admin'],
            'status' => ['sometimes', 'in:active,blocked,deactivated'],
        ]);

        $oldStatus = $user->status;
        $user->update($data);

        // Record Audit Log
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            AdminActivityLog::create([
                'admin_id' => $request->user()?->id,
                'admin_name' => $request->user()?->name ?? 'System Admin',
                'action' => 'UPDATE_USER_STATUS',
                'description' => "User #{$user->id} ({$user->name}) status updated to {$user->status}",
                'ip_address' => $request->ip(),
            ]);
        }

        if (isset($data['role'])) {
            AdminActivityLog::create([
                'admin_id' => $request->user()?->id,
                'admin_name' => $request->user()?->name ?? 'System Admin',
                'action' => 'CHANGE_USER_ROLE',
                'description' => "User #{$user->id} ({$user->name}) role updated to {$user->role}",
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json([
            'message' => 'User account updated successfully',
            'data' => $user,
        ]);
    }

    public function block(User $user, Request $request)
    {
        $user->update(['status' => $user->status === 'blocked' ? 'active' : 'blocked']);

        AdminActivityLog::create([
            'admin_id' => $request->user()?->id,
            'admin_name' => $request->user()?->name ?? 'System Admin',
            'action' => 'TOGGLE_USER_BLOCK',
            'description' => "User #{$user->id} ({$user->name}) set to {$user->status}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['status' => $user->status, 'data' => $user]);
    }

    public function destroy(User $user, Request $request)
    {
        AdminActivityLog::create([
            'admin_id' => $request->user()?->id,
            'admin_name' => $request->user()?->name ?? 'System Admin',
            'action' => 'DELETE_USER',
            'description' => "Deleted User #{$user->id} ({$user->name})",
            'ip_address' => $request->ip(),
        ]);

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * Delete Blood Request Record
     */
    public function deleteRequest($id, Request $request)
    {
        $bloodReq = BloodRequest::findOrFail($id);

        AdminActivityLog::create([
            'admin_id' => $request->user()?->id,
            'admin_name' => $request->user()?->name ?? 'System Admin',
            'action' => 'DELETE_REQUEST',
            'description' => "Deleted Blood Request #{$bloodReq->id}",
            'ip_address' => $request->ip(),
        ]);

        $bloodReq->delete();

        return response()->json(['message' => 'Blood request deleted successfully.']);
    }

    /**
     * Donors Moderation List
     */
    public function getDonors(Request $request)
    {
        $query = User::where('role', 'donor');

        if ($request->filled('blood_group') && $request->blood_group !== 'all') {
            $query->where('blood_group', $request->blood_group);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = strtolower(trim($request->search));
            $query->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$s}%"])
                  ->orWhere('phone', 'LIKE', "%{$s}%")
                  ->orWhereRaw('LOWER(address) LIKE ?', ["%{$s}%"]);
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'data' => $paginated->items(),
        ]);
    }

    /**
     * Blood Requests Moderation List
     */
    public function getRequests(Request $request)
    {
        $query = BloodRequest::query();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('urgency') && $request->urgency !== 'all') {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('blood_group') && $request->blood_group !== 'all') {
            $query->where('blood_group', $request->blood_group);
        }

        if ($request->filled('search')) {
            $s = strtolower(trim($request->search));
            $query->where(function ($q) use ($s) {
                if (Schema::hasColumn('blood_requests', 'patient_name')) {
                    $q->orWhereRaw('LOWER(patient_name) LIKE ?', ["%{$s}%"]);
                }
                if (Schema::hasColumn('blood_requests', 'hospital')) {
                    $q->orWhereRaw('LOWER(hospital) LIKE ?', ["%{$s}%"]);
                }
                if (Schema::hasColumn('blood_requests', 'hospital_name')) {
                    $q->orWhereRaw('LOWER(hospital_name) LIKE ?', ["%{$s}%"]);
                }
                if (Schema::hasColumn('blood_requests', 'city')) {
                    $q->orWhereRaw('LOWER(city) LIKE ?', ["%{$s}%"]);
                }
                if (Schema::hasColumn('blood_requests', 'contact_number')) {
                    $q->orWhere('contact_number', 'LIKE', "%{$s}%");
                }
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'data' => $paginated->items(),
        ]);
    }

    /**
     * Update Blood Request Moderation Status
     */
    public function updateRequest(Request $request, $id)
    {
        $bloodReq = BloodRequest::findOrFail($id);
        
        $request->validate([
            'status' => 'required|in:open,pending,in_progress,matched,fulfilled,cancelled',
        ]);

        $oldStatus = $bloodReq->status;
        $bloodReq->status = $request->status;
        $bloodReq->save();

        AdminActivityLog::create([
            'admin_id' => $request->user()?->id,
            'admin_name' => $request->user()?->name ?? 'System Admin',
            'action' => 'UPDATE_REQUEST_STATUS',
            'description' => "Request #{$bloodReq->id} status changed from {$oldStatus} to {$bloodReq->status}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Request updated successfully',
            'data' => $bloodReq,
        ]);
    }

    /**
     * Audit Activity Logs
     */
    public function getLogs()
    {
        $logs = AdminActivityLog::latest()->take(50)->get();
        return response()->json([
            'total' => $logs->count(),
            'data' => $logs,
        ]);
    }

    /**
     * Admin Change Password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $user = $request->user();

        if (! $user) {
            $user = User::where('email', strtolower($request->string('admin_email')))->first();
        }

        if (! $user) {
            return response()->json(['message' => 'Admin account not found.'], 404);
        }

        $currentValid = false;
        try {
            if (Hash::check($request->string('current_password'), $user->password)) {
                $currentValid = true;
            }
        } catch (\Throwable $e) {
            if ($request->string('current_password') === $user->password) {
                $currentValid = true;
            }
        }

        if (! $currentValid) {
            return response()->json(['message' => 'Current password is incorrect.'], 400);
        }

        $user->password = Hash::make($request->string('new_password'));
        $user->save();

        AdminActivityLog::create([
            'admin_id' => $user->id,
            'admin_name' => $user->name,
            'action' => 'CHANGE_PASSWORD',
            'description' => "Password changed successfully for admin ({$user->email})",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Admin password changed successfully!']);
    }
}

