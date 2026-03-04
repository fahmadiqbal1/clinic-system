@extends('layouts.app')

@section('title', 'Edit Expense')

@section('content')
<div class="fade-in">
    <div class="mb-4">
        <a href="{{ route('owner.expenses.index') }}" class="btn btn-outline-secondary btn-sm mb-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Expenses
        </a>
        <h1 class="page-header"><i class="bi bi-pencil-square me-2"></i>Edit Expense</h1>
        <p class="page-subtitle">Update expense details</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="glass-card fade-in delay-1">
                <form action="{{ route('owner.expenses.update', $expense) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="form-section">
                        <h6 class="form-section-title"><i class="bi bi-card-list me-2"></i>Expense Details</h6>

                        <div class="mb-3">
                            <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                            <select name="department" id="department" class="form-select @error('department') is-invalid @enderror" required>
                                <option value="">Select department</option>
                                <option value="lab" {{ old('department', $expense->department) === 'lab' ? 'selected' : '' }}>Laboratory</option>
                                <option value="radiology" {{ old('department', $expense->department) === 'radiology' ? 'selected' : '' }}>Radiology</option>
                                <option value="pharmacy" {{ old('department', $expense->department) === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                                <option value="consultation" {{ old('department', $expense->department) === 'consultation' ? 'selected' : '' }}>Consultation</option>
                                <option value="general" {{ old('department', $expense->department) === 'general' ? 'selected' : '' }}>General</option>
                            </select>
                            @error('department')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="fixed" {{ old('category', $expense->category) === 'fixed' ? 'selected' : '' }}>Fixed (Rent, Salaries, Bills)</option>
                                <option value="variable" {{ old('category', $expense->category) === 'variable' ? 'selected' : '' }}>Variable (Supplies, Misc)</option>
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
                                required>{{ old('description', $expense->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="cost" class="form-label">Cost ({{ currency_symbol() }}) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                <input type="number" step="0.01" min="0.01" name="cost" id="cost"
                                    value="{{ old('cost', $expense->cost) }}"
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
                            <i class="bi bi-check-lg me-1"></i> Update Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection