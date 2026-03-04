@extends('layouts.app')

@section('title', 'Add Expense')

@section('content')
<div class="fade-in">
    <div class="mb-4">
        <a href="{{ route('owner.expenses.index') }}" class="btn btn-outline-secondary btn-sm mb-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Expenses
        </a>
        <h1 class="page-header"><i class="bi bi-plus-circle me-2"></i>Add Expense</h1>
        <p class="page-subtitle">Record a manual clinic expense</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="glass-card fade-in delay-1">
                @if($errors->any())
                    <div class="alert-banner-danger mb-4">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <ul class="mb-0 ms-2">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('owner.expenses.store') }}" method="POST">
                    @csrf

                    <div class="form-section">
                        <h6 class="form-section-title"><i class="bi bi-card-list me-2"></i>Expense Details</h6>

                        <div class="mb-3">
                            <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                            <select name="department" id="department" class="form-select @error('department') is-invalid @enderror" required>
                                <option value="">Select department</option>
                                <option value="lab" {{ old('department') === 'lab' ? 'selected' : '' }}>Laboratory</option>
                                <option value="radiology" {{ old('department') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                                <option value="pharmacy" {{ old('department') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                                <option value="consultation" {{ old('department') === 'consultation' ? 'selected' : '' }}>Consultation</option>
                                <option value="general" {{ old('department') === 'general' ? 'selected' : '' }}>General</option>
                            </select>
                            @error('department')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="fixed" {{ old('category') === 'fixed' ? 'selected' : '' }}>Fixed (Rent, Salaries, Bills)</option>
                                <option value="variable" {{ old('category', 'variable') === 'variable' ? 'selected' : '' }}>Variable (Supplies, Misc)</option>
                            </select>
                            <div class="form-text"><i class="bi bi-info-circle me-1"></i>Fixed = recurring monthly costs. Variable = one-time or irregular costs.</div>
                            @error('category')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="description" rows="3"
                                class="form-control @error('description') is-invalid @enderror"
                                placeholder="Describe the expense" required>{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="cost" class="form-label">Cost ({{ currency_symbol() }}) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                <input type="number" step="0.01" min="0.01" name="cost" id="cost"
                                    value="{{ old('cost') }}"
                                    class="form-control @error('cost') is-invalid @enderror" required>
                            </div>
                            @error('cost')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="glass-divider"></div>

                    <div class="d-flex gap-2 pt-3">
                        <a href="{{ route('owner.expenses.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Record Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection