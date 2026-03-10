<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiAnalysisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Doctor', 'Laboratory', 'Radiology']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'context_type' => ['required', 'in:consultation,lab,radiology'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient selection is required for AI analysis.',
            'context_type.required' => 'Analysis context type is required.',
            'context_type.in' => 'Invalid context type. Must be consultation, lab, or radiology.',
        ];
    }
}
