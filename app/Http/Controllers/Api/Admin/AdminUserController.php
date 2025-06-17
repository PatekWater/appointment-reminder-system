<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminUserController extends Controller
{
    /**
     * Display a listing of all users in the system.
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        $query = User::with(['clients', 'appointments']);

        // Filter by admin status
        if ($request->has('is_admin')) {
            if ($request->is_admin === 'true') {
                $query->admins();
            } else {
                $query->regularUsers();
            }
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'All users retrieved successfully',
            'data' => $users
        ], Response::HTTP_OK);
    }

    /**
     * Display the specified user.
     * GET /api/admin/users/{user}
     */
    public function show(User $user)
    {
        $user->load(['clients', 'appointments.client', 'appointments.reminderDispatches']);

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified user.
     * PUT /api/admin/users/{user}
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'timezone' => 'sometimes|nullable|string|max:255',
            'is_admin' => 'sometimes|boolean',
        ]);

        $user->update($request->only(['name', 'email', 'timezone', 'is_admin']));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified user.
     * DELETE /api/admin/users/{user}
     */
    public function destroy(User $user)
    {
        // Prevent admin from deleting themselves
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Get user statistics.
     * GET /api/admin/users/stats
     */
    public function stats()
    {
        $stats = [
            'total_users' => User::count(),
            'admin_users' => User::admins()->count(),
            'regular_users' => User::regularUsers()->count(),
            'users_with_appointments' => User::has('appointments')->count(),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'users_by_month' => User::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
                                   ->groupBy('month')
                                   ->orderBy('month', 'desc')
                                   ->limit(12)
                                   ->get(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'User statistics retrieved successfully',
            'data' => $stats
        ], Response::HTTP_OK);
    }

    /**
     * Toggle admin status for a user.
     * PATCH /api/admin/users/{user}/toggle-admin
     */
    public function toggleAdmin(User $user)
    {
        // Prevent admin from removing their own admin status
        if ($user->id === auth()->id() && $user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove your own admin privileges'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->update([
            'is_admin' => !$user->is_admin
        ]);

        $status = $user->is_admin ? 'granted' : 'revoked';

        return response()->json([
            'success' => true,
            'message' => "Admin privileges {$status} successfully",
            'data' => $user
        ], Response::HTTP_OK);
    }
}
