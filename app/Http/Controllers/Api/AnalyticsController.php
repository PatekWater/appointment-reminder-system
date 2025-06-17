<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ReminderDispatch;
use App\Models\AppointmentReminder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get analytics data for the authenticated user or system-wide for admins.
     * GET /api/analytics?period=day|week|month
     */
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');
        $user = $request->user();
        
        // Determine if this is a system-wide request (admin) or user-specific
        $isSystemWide = $user->isAdmin() && $request->get('system_wide', false);
        
        $dateRange = $this->getDateRange($period);
        
        $analytics = [
            'period' => $period,
            'date_range' => $dateRange,
            'system_wide' => $isSystemWide,
            'reminders' => $this->getReminderAnalytics($user, $isSystemWide, $dateRange),
            'appointments' => $this->getAppointmentAnalytics($user, $isSystemWide, $dateRange),
            'trends' => $this->getTrendAnalytics($user, $isSystemWide, $period),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Analytics retrieved successfully',
            'data' => $analytics
        ], Response::HTTP_OK);
    }

    /**
     * Get reminder analytics.
     */
    private function getReminderAnalytics($user, $isSystemWide, $dateRange)
    {
        $query = $isSystemWide 
            ? ReminderDispatch::query()
            : ReminderDispatch::whereHas('appointment', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        return [
            'sent_reminders' => (clone $query)->sent()->count(),
            'failed_reminders' => (clone $query)->failed()->count(),
            'upcoming_reminders' => (clone $query)->scheduled()->count(),
            'total_reminders' => $query->count(),
            'success_rate' => $this->calculateSuccessRate($query),
        ];
    }

    /**
     * Get appointment analytics.
     */
    private function getAppointmentAnalytics($user, $isSystemWide, $dateRange)
    {
        $query = $isSystemWide 
            ? Appointment::query()
            : Appointment::where('user_id', $user->id);

        $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        return [
            'appointments_by_status' => [
                'scheduled' => (clone $query)->status('scheduled')->count(),
                'completed' => (clone $query)->status('completed')->count(),
                'cancelled' => (clone $query)->status('cancelled')->count(),
                'missed' => (clone $query)->status('missed')->count(),
            ],
            'total_appointments' => $query->count(),
            'upcoming_appointments' => (clone $query)->upcoming()->count(),
            'past_appointments' => (clone $query)->past()->count(),
            'recurring_appointments' => (clone $query)->recurring()->count(),
        ];
    }

    /**
     * Get trend analytics.
     */
    private function getTrendAnalytics($user, $isSystemWide, $period)
    {
        $groupBy = match($period) {
            'day' => 'DATE(created_at)',
            'week' => 'YEARWEEK(created_at)',
            'month' => 'DATE_FORMAT(created_at, "%Y-%m")',
            default => 'DATE_FORMAT(created_at, "%Y-%m")'
        };

        $appointmentsQuery = $isSystemWide 
            ? Appointment::query()
            : Appointment::where('user_id', $user->id);

        $remindersQuery = $isSystemWide 
            ? ReminderDispatch::query()
            : ReminderDispatch::whereHas('appointment', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        return [
            'appointments_over_time' => $appointmentsQuery
                ->selectRaw("{$groupBy} as period, COUNT(*) as count")
                ->groupBy('period')
                ->orderBy('period', 'desc')
                ->limit(12)
                ->get(),
            'reminders_over_time' => $remindersQuery
                ->selectRaw("{$groupBy} as period, COUNT(*) as count")
                ->groupBy('period')
                ->orderBy('period', 'desc')
                ->limit(12)
                ->get(),
        ];
    }

    /**
     * Get date range based on period.
     */
    private function getDateRange($period)
    {
        $now = Carbon::now();

        return match($period) {
            'day' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            default => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ]
        };
    }

    /**
     * Calculate success rate for reminders.
     */
    private function calculateSuccessRate($query)
    {
        $total = (clone $query)->count();
        if ($total === 0) {
            return 0;
        }

        $sent = (clone $query)->sent()->count();
        return round(($sent / $total) * 100, 2);
    }

    /**
     * Get detailed reminder statistics.
     * GET /api/analytics/reminders
     */
    public function reminderStats(Request $request)
    {
        $user = $request->user();
        $isSystemWide = $user->isAdmin() && $request->get('system_wide', false);

        $query = $isSystemWide 
            ? ReminderDispatch::query()
            : ReminderDispatch::whereHas('appointment', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        $stats = [
            'total' => $query->count(),
            'by_status' => [
                'scheduled' => (clone $query)->scheduled()->count(),
                'sent' => (clone $query)->sent()->count(),
                'failed' => (clone $query)->failed()->count(),
            ],
            'by_method' => [
                'email' => (clone $query)->where('method', 'email')->count(),
                'sms' => (clone $query)->where('method', 'sms')->count(),
            ],
            'recent_failures' => (clone $query)->failed()
                ->with('appointment.client')
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Reminder statistics retrieved successfully',
            'data' => $stats
        ], Response::HTTP_OK);
    }
}
