<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $patient = $this->route('patient');
        return $this->user()->can('update', $patient);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['sometimes', 'required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'consultation_notes' => ['nullable', 'string', 'max:65535'],
            'status' => ['sometimes', Rule::in(['registered', 'awaiting_triage', 'in_triage', 'awaiting_doctor', 'with_doctor', 'completed'])],
        ];
    }
}
