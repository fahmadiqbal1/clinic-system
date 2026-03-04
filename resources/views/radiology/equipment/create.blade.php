@extends('layouts.app')
@section('title', 'Add Radiology Equipment — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-plus-circle me-2" style="color:var(--accent-success);"></i>Add Radiology Equipment</h2>
            <p class="page-subtitle mb-0">Register new imaging instrument</p>
        </div>
        <a href="{{ route('radiology.equipment.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <div class="glass-card p-4 fade-in delay-1" style="max-width:900px;">
            <form method="POST" action="{{ route('radiology.equipment.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Equipment Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" required placeholder="e.g. X-Ray Machine">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control @error('model') is-invalid @enderror"
                            value="{{ old('model') }}" placeholder="e.g. GE Discovery XR656">
                        @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror"
                            value="{{ old('serial_number') }}">
                        @error('serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="operational" {{ old('status') === 'operational' ? 'selected' : '' }}>Operational</option>
                            <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                            <option value="out_of_service" {{ old('status') === 'out_of_service' ? 'selected' : '' }}>Out of Service</option>
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Maintenance Date</label>
                        <input type="date" name="last_maintenance_date" class="form-control @error('last_maintenance_date') is-invalid @enderror"
                            value="{{ old('last_maintenance_date') }}">
                        @error('last_maintenance_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Next Maintenance Date</label>
                        <input type="date" name="next_maintenance_date" class="form-control @error('next_maintenance_date') is-invalid @enderror"
                            value="{{ old('next_maintenance_date') }}">
                        @error('next_maintenance_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror"
                            rows="3" placeholder="Equipment specifications, calibration notes, etc.">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Equipment</button>
                    <a href="{{ route('radiology.equipment.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
    </div>
</div>
@endsection
