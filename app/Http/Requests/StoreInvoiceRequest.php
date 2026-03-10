<?php

namespace App\Http\Requests;

use App\Enums\InvoiceDepartment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Receptionist', 'Doctor', 'Laboratory', 'Radiology', 'Pharmacy']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'department' => ['required', Rule::in(InvoiceDepartment::values())],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_catalog_id' => ['required_without:items.*.inventory_item_id', 'exists:service_catalog,id'],
            'items.*.inventory_item_id' => ['required_without:items.*.service_catalog_id', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['required_with:discount_amount', 'string', 'max:500'],
            'prescribing_doctor_id' => ['nullable', 'exists:users,id'],
            'referrer_name' => ['nullable', 'string', 'max:255'],
            'referrer_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'visit_id' => ['nullable', 'exists:visits,id'],
            'prescription_id' => ['nullable', 'exists:prescriptions,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient selection is required.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'department.required' => 'Invoice department is required.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
        ];
    }
}
