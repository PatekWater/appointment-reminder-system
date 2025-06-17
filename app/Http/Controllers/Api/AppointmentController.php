<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /api/appointments?status=upcoming|past
     */
    public function index(Request $request)
    {
        $query = $request->user()->appointments()->with(['client', 'reminderDispatches']);

        // Filter by status if provided
        if ($request->has('status')) {
            switch ($request->status) {
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'past':
                    $query->past();
                    break;
            }
        }

        $appointments = $query->orderBy('appointment_time', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Appointments retrieved successfully',
            'data' => $appointments
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/appointments
     */
    public function store(StoreAppointmentRequest $request)
    {
        // Convert appointment time to UTC for storage
        $appointmentTime = Carbon::parse($request->appointment_time, $request->timezone)
            ->utc();

        $appointmentData = [
            'client_id' => $request->client_id,
            'title' => $request->title,
            'description' => $request->description,
            'appointment_time' => $appointmentTime,
            'timezone' => $request->timezone,
            'status' => 'scheduled',
        ];

        // Handle recurring appointment fields (bonus feature)
        if ($request->has('is_recurring') && $request->boolean('is_recurring')) {
            $appointmentData['is_recurring'] = true;
            $appointmentData['recurrence_rule'] = $request->recurrence_rule;
            $appointmentData['parent_appointment_id'] = null; // This is a master recurring appointment
        }

        // Handle custom reminder offsets (bonus feature)
        if ($request->has('reminder_offsets') && is_array($request->reminder_offsets)) {
            $appointmentData['reminder_offsets'] = $request->reminder_offsets;
        }

        $appointment = $request->user()->appointments()->create($appointmentData);

        $appointment->load(['client', 'reminderDispatches', 'appointmentReminders']);

        $message = $appointment->is_recurring 
            ? 'Recurring appointment created successfully' 
            : 'Appointment created successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $appointment
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     * GET /api/appointments/{appointment}
     */
    public function show(Request $request, Appointment $appointment)
    {
        // Ensure the appointment belongs to the authenticated user
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $appointment->load(['client', 'reminderDispatches']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment retrieved successfully',
            'data' => $appointment
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     * PUT/PATCH /api/appointments/{appointment}
     */
    public function update(Request $request, Appointment $appointment)
    {
        // Ensure the appointment belongs to the authenticated user
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'client_id' => 'sometimes|required|exists:clients,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'appointment_time' => 'sometimes|required|date|after:now',
            'timezone' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // If client_id is being updated, verify it belongs to the user
        if ($request->has('client_id')) {
            $client = Client::find($request->client_id);
            if (!$client || $client->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], Response::HTTP_NOT_FOUND);
            }
        }

        $updateData = $request->only(['client_id', 'title', 'description', 'timezone']);

        // Handle timezone-aware datetime update
        if ($request->has('appointment_time')) {
            $timezone = $request->has('timezone') ? $request->timezone : $appointment->timezone;
            $appointmentTime = Carbon::parse($request->appointment_time, $timezone)->utc();
            $updateData['appointment_time'] = $appointmentTime;
        }

        $appointment->update($updateData);
        $appointment->load(['client', 'reminderDispatches']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment updated successfully',
            'data' => $appointment
        ], Response::HTTP_OK);
    }

    /**
     * Update the appointment status (Bonus Feature).
     * PATCH /api/appointments/{appointment}/status
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        // Ensure the appointment belongs to the authenticated user
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'status' => 'required|in:scheduled,completed,cancelled,missed'
        ]);

        $oldStatus = $appointment->status;
        $appointment->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => "Appointment status updated from '{$oldStatus}' to '{$request->status}'",
            'data' => $appointment
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/appointments/{appointment}
     */
    public function destroy(Request $request, Appointment $appointment)
    {
        // Ensure the appointment belongs to the authenticated user
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $appointment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Appointment deleted successfully'
        ], Response::HTTP_OK);
    }
}
