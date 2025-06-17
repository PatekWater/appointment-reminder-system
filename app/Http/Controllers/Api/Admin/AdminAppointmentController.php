<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminAppointmentController extends Controller
{
    /**
     * Display a listing of all appointments in the system.
     * GET /api/admin/appointments
     */
    public function index(Request $request)
    {
        $query = Appointment::with(['user', 'client', 'reminderDispatches']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->status($request->status);
        }

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('appointment_time', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('appointment_time', '<=', $request->date_to);
        }

        // Search by title or client name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $appointments = $query->orderBy('appointment_time', 'desc')
                             ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'All appointments retrieved successfully',
            'data' => $appointments
        ], Response::HTTP_OK);
    }

    /**
     * Display the specified appointment.
     * GET /api/admin/appointments/{appointment}
     */
    public function show(Appointment $appointment)
    {
        $appointment->load(['user', 'client', 'reminderDispatches', 'appointmentReminders']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment retrieved successfully',
            'data' => $appointment
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified appointment status.
     * PATCH /api/admin/appointments/{appointment}/status
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $request->validate([
            'status' => 'required|in:scheduled,completed,cancelled,missed'
        ]);

        $appointment->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Appointment status updated successfully',
            'data' => $appointment
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified appointment.
     * DELETE /api/admin/appointments/{appointment}
     */
    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Appointment deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Get appointment statistics.
     * GET /api/admin/appointments/stats
     */
    public function stats()
    {
        $stats = [
            'total_appointments' => Appointment::count(),
            'upcoming_appointments' => Appointment::upcoming()->count(),
            'past_appointments' => Appointment::past()->count(),
            'status_breakdown' => [
                'scheduled' => Appointment::status('scheduled')->count(),
                'completed' => Appointment::status('completed')->count(),
                'cancelled' => Appointment::status('cancelled')->count(),
                'missed' => Appointment::status('missed')->count(),
            ],
            'appointments_by_month' => Appointment::selectRaw('DATE_FORMAT(appointment_time, "%Y-%m") as month, COUNT(*) as count')
                                                 ->groupBy('month')
                                                 ->orderBy('month', 'desc')
                                                 ->limit(12)
                                                 ->get(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Appointment statistics retrieved successfully',
            'data' => $stats
        ], Response::HTTP_OK);
    }
}
