<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ReminderDispatch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReminderController extends Controller
{
    /**
     * Display a listing of all reminder dispatches for the authenticated user.
     * GET /api/reminders
     */
    public function index(Request $request)
    {
        $reminders = ReminderDispatch::whereHas('appointment', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
        ->with(['appointment.client'])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Reminders retrieved successfully',
            'data' => $reminders
        ], Response::HTTP_OK);
    }

    /**
     * Display reminder dispatches for a specific appointment.
     * GET /api/appointments/{appointment}/reminders
     */
    public function appointmentReminders(Request $request, Appointment $appointment)
    {
        // Ensure the appointment belongs to the authenticated user
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $reminders = $appointment->reminderDispatches()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Appointment reminders retrieved successfully',
            'data' => $reminders
        ], Response::HTTP_OK);
    }
}
