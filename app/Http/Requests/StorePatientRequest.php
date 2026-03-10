<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Receptionist', 'Doctor']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'registration_type' => ['required', Rule::in(['walk_in', 'appointment', 'emergency', 'referral'])],
            'referred_by_user_id' => ['nullable', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Patient first name is required.',
            'last_name.required' => 'Patient last name is required.',
            'phone.required' => 'Phone number is required for patient contact.',
            'gender.required' => 'Gender selection is required.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
        ];
    }
}
