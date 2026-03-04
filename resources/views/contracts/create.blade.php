@extends('layouts.app')

@section('title', 'Create Contract — ' . config('app.name'))

@section('content')
<div class="fade-in delay-1">
    <div class="page-header">
        <div>
            <h1 class="h3 fw-bold text-white mb-1">
                <i class="bi bi-file-earmark-plus me-2"></i> Create Staff Contract
            </h1>
            <p class="page-subtitle mb-0">Draft a new employment contract for a staff member</p>
        </div>
        <a href="{{ route('contracts.index') }}" class="btn btn-outline-light fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Contracts
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="fade-in delay-2">
        <div class="alert-banner-danger d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Please correct the following errors:</strong>
                <ul class="mb-0 mt-1 list-unstyled">
                    @foreach ($errors->all() as $error)
                        <li><i class="bi bi-dot"></i> {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    {{-- Contract Form --}}
    <div class="col-lg-8">
        <div class="fade-in delay-2">
            <div class="glass-card">
                <form action="{{ route('contracts.store') }}" method="POST">
                    @csrf

                    {{-- Staff Selection --}}
                    <div class="mb-4">
                        <label for="user_id" class="form-label text-white fw-semibold">
                            <i class="bi bi-person-badge me-1"></i> Select Staff Member
                        </label>
                        <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror">
                            <option value="">— Choose a staff member —</option>
                            @foreach ($staffMembers as $doc)
                                <option value="{{ $doc->id }}" @selected(old('user_id') == $doc->id)>
                                    {{ $doc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Minimum Term --}}
                    <div class="mb-4">
                        <label for="minimum_term_months" class="form-label text-white fw-semibold">
                            <i class="bi bi-calendar-range me-1"></i> Minimum Employment Term (months)
                        </label>
                        <input type="number" name="minimum_term_months" id="minimum_term_months"
                            value="{{ old('minimum_term_months', 12) }}"
                            min="1"
                            class="form-control @error('minimum_term_months') is-invalid @enderror">
                        @error('minimum_term_months')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Effective From --}}
                    <div class="mb-4">
                        <label for="effective_from" class="form-label text-white fw-semibold">
                            <i class="bi bi-calendar-check me-1"></i> Contract Effective From
                        </label>
                        <input type="date" name="effective_from" id="effective_from"
                            value="{{ old('effective_from', now()->format('Y-m-d')) }}"
                            class="form-control @error('effective_from') is-invalid @enderror">
                        @error('effective_from')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Contract HTML --}}
                    <div class="mb-4">
                        <label for="contract_html" class="form-label text-white fw-semibold">
                            <i class="bi bi-code-slash me-1"></i> Contract Terms (HTML)
                        </label>
                        <textarea name="contract_html" id="contract_html" rows="14"
                            class="form-control font-monospace small @error('contract_html') is-invalid @enderror"
                            placeholder="Paste HTML content of the contract here..." required>{{ old('contract_html') }}</textarea>
                        <div class="form-text text-white-50">
                            <i class="bi bi-info-circle me-1"></i>
                            Enter the contract content as HTML. This will be stored as a snapshot and displayed to the staff member for signature.
                        </div>
                        @error('contract_html')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="glass-divider mb-4"></div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('contracts.index') }}" class="btn btn-outline-light">
                            <i class="bi bi-x-lg me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-check-lg me-1"></i> Create Contract
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Info Sidebar --}}
    <div class="col-lg-4">
        <div class="fade-in delay-3">
            <div class="glass-card accent-left-primary">
                <h3 class="h5 fw-bold text-white mb-3">
                    <i class="bi bi-lightbulb me-2"></i> Contract Notes
                </h3>
                <ul class="list-unstyled small text-white-50 mb-0">
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="bi bi-check2-circle text-info mt-1"></i>
                        <span>Contracts are created in <strong class="text-white">draft</strong> status and require staff signature to activate.</span>
                    </li>
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="bi bi-lock text-warning mt-1"></i>
                        <span>Once created, the contract HTML is stored as a snapshot and cannot be edited.</span>
                    </li>
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="bi bi-layers text-info mt-1"></i>
                        <span>Multiple contracts can exist for a staff member; only one can be <strong class="text-white">active</strong> at a time.</span>
                    </li>
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="bi bi-arrow-repeat text-warning mt-1"></i>
                        <span>Creating a new contract for a staff member with an active one will automatically supersede the old one.</span>
                    </li>
                    <li class="d-flex align-items-start gap-2">
                        <i class="bi bi-pen text-success mt-1"></i>
                        <span>Staff members must explicitly sign the contract before it becomes active.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection