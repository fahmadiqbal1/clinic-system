@extends('layouts.app')
@section('title', 'Edit Room — ' . config('app.name'))

@section('content')
<div class="fade-in" style="max-width:700px;">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-pencil me-2"></i>Edit Room — {{ $room->name }}</h1>
        </div>
        <a href="{{ route('owner.rooms.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="{{ route('owner.rooms.update', $room) }}">
        @csrf @method('PATCH')
        <div class="glass-card mb-3">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Room Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name', $room->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror"
                        value="{{ old('sort_order', $room->sort_order) }}" min="0">
                    @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Room Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                        @foreach($types as $type)
                            <option value="{{ $type }}" {{ old('type', $room->type) === $type ? 'selected' : '' }}>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Specialty <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="specialty" class="form-control @error('specialty') is-invalid @enderror"
                        value="{{ old('specialty', $room->specialty) }}" placeholder="e.g. Cardiology">
                    @error('specialty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Equipment Notes <span class="text-muted small">(optional)</span></label>
                    <textarea name="equipment_notes" class="form-control @error('equipment_notes') is-invalid @enderror"
                        rows="3">{{ old('equipment_notes', $room->equipment_notes) }}</textarea>
                    @error('equipment_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                            {{ old('is_active', $room->is_active ? '1' : '') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            <strong>Active</strong>
                            <div class="small text-muted">Inactive rooms are hidden from scheduling.</div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            <a href="{{ route('owner.rooms.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
