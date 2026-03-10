<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTriageVitalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Triage']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'visit_id' => ['nullable', 'exists:visits,id'],
            'blood_pressure' => ['nullable', 'string', 'max:20', 'regex:/^\d{2,3}\/\d{2,3}$/'],
            'temperature' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'pulse_rate' => ['nullable', 'integer', 'min:20', 'max:250'],
            'respiratory_rate' => ['nullable', 'integer', 'min:5', 'max:60'],
            'weight' => ['nullable', 'numeric', 'min:0.5', 'max:500'],
            'height' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'oxygen_saturation' => ['nullable', 'numeric', 'min:50', 'max:100'],
            'chief_complaint' => ['required', 'string', 'max:1000'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'blood_pressure.regex' => 'Blood pressure must be in format like 120/80.',
            'temperature.min' => 'Temperature seems too low (min 30°C).',
            'temperature.max' => 'Temperature seems too high (max 45°C).',
            'chief_complaint.required' => 'Chief complaint is required for triage.',
            'priority.required' => 'Priority level is required.',
        ];
    }
}
