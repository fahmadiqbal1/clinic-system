@extends('layouts.app')
@section('title', 'Create Prescription — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-prescription2 me-2" style="color:var(--accent-success);"></i>New Prescription</h2>
            <p class="page-subtitle mb-0">For {{ $patient->first_name }} {{ $patient->last_name }} (ID #{{ $patient->id }})</p>
        </div>
        <a href="{{ route('doctor.consultation.show', $patient) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Consultation</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger fade-in">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('doctor.prescriptions.store', $patient) }}" method="POST" id="prescription-form">
        @csrf

        {{-- Diagnosis & Notes --}}
        <div class="card mb-4 fade-in delay-1">
            <div class="card-header"><i class="bi bi-journal-medical me-2" style="color:var(--accent-info);"></i>Diagnosis & Notes</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="diagnosis" class="form-label">Diagnosis</label>
                        <textarea name="diagnosis" id="diagnosis" class="form-control" rows="3" placeholder="Enter diagnosis...">{{ old('diagnosis') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any special instructions for pharmacist...">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Medication Items --}}
        <div class="card mb-4 fade-in delay-2">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-capsule me-2" style="color:var(--accent-warning);"></i>Medications</span>
                <button type="button" class="btn btn-outline-primary btn-sm" id="add-medication-row">
                    <i class="bi bi-plus-circle me-1"></i>Add Medication
                </button>
            </div>
            <div class="card-body">
                <div id="medication-rows">
                    <div class="medication-row border rounded p-3 mb-3" data-index="0">
                        <div class="d-flex justify-content-between mb-2">
                            <strong class="text-muted">Medication #1</strong>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-medication" style="display:none;">&times; Remove</button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Medication / Procedure *</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control medication-search" placeholder="Search medications or procedures..." autocomplete="off" data-index="0">
                                    <input type="hidden" name="items[0][inventory_item_id]" class="medication-id">
                                    <div class="dropdown-menu medication-dropdown w-100" style="max-height:250px;overflow-y:auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Medication Name *</label>
                                <input type="text" name="items[0][medication_name]" class="form-control medication-name" required placeholder="e.g. Amoxicillin 500mg">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Dosage</label>
                                <input type="text" name="items[0][dosage]" class="form-control" placeholder="e.g. 500mg">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Frequency</label>
                                <input type="text" name="items[0][frequency]" class="form-control" placeholder="e.g. 3x daily">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Duration</label>
                                <input type="text" name="items[0][duration]" class="form-control" placeholder="e.g. 7 days">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Quantity *</label>
                                <input type="number" name="items[0][quantity]" class="form-control" min="1" value="1" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Special Instructions</label>
                                <input type="text" name="items[0][instructions]" class="form-control" placeholder="e.g. Take after meals">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="d-flex gap-2 fade-in delay-3">
            <button type="submit" class="btn btn-success" onclick="return confirm('Create this prescription and send to pharmacy?')">
                <i class="bi bi-check-circle me-1"></i>Create Prescription
            </button>
            <a href="{{ route('doctor.consultation.show', $patient) }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Build searchable items array from server data
    const allItems = [];

    @foreach($medications as $med)
    allItems.push({
        id: '{{ $med->id }}',
        name: @json($med->name),
        extra: @json($med->chemical_formula ?? ''),
        group: 'Pharmacy Medications',
        type: 'medication'
    });
    @endforeach

    @foreach($services as $svc)
    allItems.push({
        id: '',
        name: @json($svc->name),
        extra: @json($svc->code),
        group: @json(ucfirst($svc->department)) + ' Services',
        type: 'service'
    });
    @endforeach

    let rowIndex = 1;
    const container = document.getElementById('medication-rows');
    const addBtn = document.getElementById('add-medication-row');

    // Searchable dropdown logic
    function initSearchDropdown(row) {
        const searchInput = row.querySelector('.medication-search');
        const dropdown = row.querySelector('.medication-dropdown');
        const hiddenId = row.querySelector('.medication-id');
        const nameInput = row.querySelector('.medication-name');

        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            if (q.length < 1) { dropdown.classList.remove('show'); return; }

            const matches = allItems.filter(i =>
                i.name.toLowerCase().includes(q) ||
                i.extra.toLowerCase().includes(q) ||
                i.group.toLowerCase().includes(q)
            ).slice(0, 20);

            if (matches.length === 0) {
                dropdown.innerHTML = '<div class="dropdown-item text-muted small">No matches — type name manually</div>';
                dropdown.classList.add('show');
                return;
            }

            let html = '';
            let lastGroup = '';
            matches.forEach(item => {
                if (item.group !== lastGroup) {
                    html += `<h6 class="dropdown-header">${item.group}</h6>`;
                    lastGroup = item.group;
                }
                const extra = item.extra ? ` <small class="text-muted">(${item.extra})</small>` : '';
                html += `<a href="#" class="dropdown-item" data-id="${item.id}" data-name="${item.name.replace(/"/g, '&quot;')}">${item.name}${extra}</a>`;
            });
            dropdown.innerHTML = html;
            dropdown.classList.add('show');
        });

        searchInput.addEventListener('blur', function() {
            setTimeout(() => dropdown.classList.remove('show'), 200);
        });

        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length > 0) this.dispatchEvent(new Event('input'));
        });

        dropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const item = e.target.closest('.dropdown-item[data-name]');
            if (!item) return;
            searchInput.value = item.dataset.name;
            hiddenId.value = item.dataset.id || '';
            nameInput.value = item.dataset.name;
            dropdown.classList.remove('show');
        });
    }

    // Initialise first row
    container.querySelectorAll('.medication-row').forEach(r => initSearchDropdown(r));

    addBtn.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'medication-row border rounded p-3 mb-3';
        row.dataset.index = rowIndex;
        row.innerHTML = `
            <div class="d-flex justify-content-between mb-2">
                <strong class="text-muted">Medication #${rowIndex + 1}</strong>
                <button type="button" class="btn btn-outline-danger btn-sm remove-medication">&times; Remove</button>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Medication / Procedure *</label>
                    <div class="position-relative">
                        <input type="text" class="form-control medication-search" placeholder="Search medications or procedures..." autocomplete="off" data-index="${rowIndex}">
                        <input type="hidden" name="items[${rowIndex}][inventory_item_id]" class="medication-id">
                        <div class="dropdown-menu medication-dropdown w-100" style="max-height:250px;overflow-y:auto;"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Medication Name *</label>
                    <input type="text" name="items[${rowIndex}][medication_name]" class="form-control medication-name" required placeholder="e.g. Amoxicillin 500mg">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dosage</label>
                    <input type="text" name="items[${rowIndex}][dosage]" class="form-control" placeholder="e.g. 500mg">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Frequency</label>
                    <input type="text" name="items[${rowIndex}][frequency]" class="form-control" placeholder="e.g. 3x daily">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Duration</label>
                    <input type="text" name="items[${rowIndex}][duration]" class="form-control" placeholder="e.g. 7 days">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity *</label>
                    <input type="number" name="items[${rowIndex}][quantity]" class="form-control" min="1" value="1" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Special Instructions</label>
                    <input type="text" name="items[${rowIndex}][instructions]" class="form-control" placeholder="e.g. Take after meals">
                </div>
            </div>`;
        container.appendChild(row);
        initSearchDropdown(row);
        rowIndex++;
        updateRemoveButtons();
    });

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-medication') || e.target.closest('.remove-medication')) {
            e.target.closest('.medication-row').remove();
            updateRemoveButtons();
        }
    });

    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.medication-row');
        rows.forEach(function(row) {
            const btn = row.querySelector('.remove-medication');
            if (btn) btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
        });
    }
});
</script>
@endpush
@endsection
