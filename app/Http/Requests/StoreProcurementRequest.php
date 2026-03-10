<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcurementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'department' => ['required', 'string', 'in:pharmacy,lab,radiology'],
            'type' => ['required', 'string', 'in:inventory,service,equipment_change,catalog_change'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required_if:type,inventory', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required_with:items', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'change_payload' => ['required_if:type,equipment_change,catalog_change', 'array'],
            'change_action' => ['required_if:type,equipment_change,catalog_change', 'in:create,update,delete'],
            'target_model' => ['required_if:type,equipment_change,catalog_change', 'string'],
            'target_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'department.required' => 'Department is required.',
            'type.required' => 'Request type is required.',
            'items.required_if' => 'At least one item is required for inventory requests.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
