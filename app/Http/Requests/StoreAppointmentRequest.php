<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow authenticated users to create appointments
        // Additional check to ensure the client belongs to the user is done in validation
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
                function ($attribute, $value, $fail) {
                    $client = Client::find($value);
                    if (!$client || $client->user_id !== auth()->id()) {
                        $fail('The selected client is invalid or does not belong to you.');
                    }
                }
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'appointment_time' => 'required|date|after:now',
            'timezone' => 'required|string|max:255|in:' . implode(',', timezone_identifiers_list()),
            
            // Recurring appointment fields (bonus feature)
            'is_recurring' => 'sometimes|boolean',
            'recurrence_rule' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf($this->boolean('is_recurring')),
                function ($attribute, $value, $fail) {
                    if ($this->boolean('is_recurring') && $value) {
                        // Basic validation for RRULE format
                        if (!$this->isValidRecurrenceRule($value)) {
                            $fail('The recurrence rule format is invalid. Use format like: FREQ=WEEKLY;UNTIL=20241231T000000Z');
                        }
                    }
                }
            ],
            
            // Custom reminder offsets (bonus feature)
            'reminder_offsets' => 'nullable|array|max:10',
            'reminder_offsets.*' => [
                'string',
                'regex:/^\d+\s+(minute|minutes|hour|hours|day|days|week|weeks)$/',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Please select a client for this appointment.',
            'client_id.exists' => 'The selected client does not exist.',
            'title.required' => 'Please provide a title for the appointment.',
            'appointment_time.required' => 'Please specify the appointment date and time.',
            'appointment_time.after' => 'The appointment must be scheduled for a future date and time.',
            'timezone.required' => 'Please specify the timezone for the appointment.',
            'timezone.in' => 'The specified timezone is not valid.',
            'recurrence_rule.required_if' => 'A recurrence rule is required for recurring appointments.',
            'reminder_offsets.array' => 'Reminder offsets must be an array.',
            'reminder_offsets.max' => 'You can specify a maximum of 10 reminder offsets.',
            'reminder_offsets.*.regex' => 'Each reminder offset must be in format like "1 hour", "2 days", "30 minutes".',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'client',
            'appointment_time' => 'appointment date and time',
            'is_recurring' => 'recurring appointment',
            'recurrence_rule' => 'recurrence rule',
            'reminder_offsets' => 'reminder offsets',
        ];
    }

    /**
     * Validate recurrence rule format.
     * Basic validation for RRULE format (RFC 5545).
     */
    protected function isValidRecurrenceRule(?string $rule): bool
    {
        if (empty($rule)) {
            return false;
        }

        // Split by semicolon to get individual parts
        $parts = explode(';', $rule);
        $hasFreq = false;

        foreach ($parts as $part) {
            if (strpos($part, '=') === false) {
                return false;
            }

            [$key, $value] = explode('=', $part, 2);
            
            switch ($key) {
                case 'FREQ':
                    $hasFreq = true;
                    if (!in_array($value, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'])) {
                        return false;
                    }
                    break;
                case 'UNTIL':
                    // Basic date format validation for UNTIL (YYYYMMDDTHHMMSSZ)
                    if (!preg_match('/^\d{8}T\d{6}Z$/', $value)) {
                        return false;
                    }
                    break;
                case 'INTERVAL':
                    if (!is_numeric($value) || (int)$value <= 0) {
                        return false;
                    }
                    break;
                case 'COUNT':
                    if (!is_numeric($value) || (int)$value <= 0) {
                        return false;
                    }
                    break;
                // Add more validation rules as needed
            }
        }

        return $hasFreq;
    }
}
