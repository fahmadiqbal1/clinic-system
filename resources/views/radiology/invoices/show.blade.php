@extends('layouts.app')
@section('title', 'Imaging Order #' . $invoice->id . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Print Header --}}
    <div class="print-header">
        <h2>{{ config('app.name') }}</h2>
        <p>Imaging Order #{{ $invoice->id }} &mdash; {{ $invoice->created_at?->format('M d, Y') }}</p>
    </div>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-broadcast me-2" style="color:var(--accent-secondary);"></i>Imaging Order #{{ $invoice->id }}</h2>
            <p class="page-subtitle mb-0">Radiology Service</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <button onclick="window.print()" class="btn btn-outline-info btn-sm" data-no-disable="true"><i class="bi bi-printer me-1"></i>Print</button>
            <a href="{{ route('radiology.invoices.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
        </div>
    </div>

    {{-- Work Completed Banner --}}
    @if($invoice->isPaid() && $invoice->isWorkCompleted())
    <div class="alert d-flex align-items-center mb-4 fade-in" style="background:rgba(var(--accent-success-rgb),0.15); border:1px solid var(--accent-success); color:var(--accent-success); border-radius:var(--radius-md);">
        <i class="bi bi-check-circle-fill me-3" style="font-size:1.5rem;"></i>
        <div>
            <strong>Work Completed</strong><br>
            <span style="color:var(--text-muted); font-size:0.9rem;">Imaging has been completed and revenue distributed. No further action required.</span>
        </div>
    </div>
    @endif

    {{-- Status & Info --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-info-circle me-2" style="color:var(--accent-info);"></i>Order Details</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Status</span>
                    @php $sStyle = match($invoice->status) { 'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);', 'paid' => 'background:rgba(var(--accent-primary-rgb),0.15);color:var(--accent-primary);', 'cancelled' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);', default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);', }; @endphp
                    <span class="badge-glass" style="{{ $sStyle }}">{{ ucfirst($invoice->status ?? 'pending') }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Order Date</span>
                    <span class="info-value">{{ $invoice->created_at?->format('M d, Y H:i') ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value">{{ $invoice->patient?->full_name ?? 'Unknown Patient' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value">{{ $invoice->patient_id ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Imaging Type</span>
                    <span class="info-value">{{ $invoice->service_name ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Payment Status</span>
                    @php
                        $payStyle = match(true) {
                            $invoice->status === 'paid' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            in_array($invoice->status, ['completed']) => 'background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);',
                            default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $payStyle }}">{{ $invoice->status === 'paid' ? 'Paid' : 'Unpaid' }}</span>
                </div>
                @if($invoice->prescribing_doctor_id)
                <div class="info-grid-item">
                    <span class="info-label">Referred by Doctor</span>
                    <span class="info-value">{{ $invoice->prescribingDoctor?->name ?? 'N/A' }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Radiology Report --}}
    @if($invoice->status === 'in_progress' || ($invoice->status === 'paid' && $invoice->performed_by_user_id && !$invoice->isWorkCompleted()))
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-file-earmark-medical me-2" style="color:var(--accent-success);"></i>Radiology Report</div>
        <div class="card-body">
            <form action="{{ route('radiology.invoices.save-report', $invoice) }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="report" class="form-label">Report Text</label>
                    <textarea name="report" id="report" class="form-control" rows="5" placeholder="Enter radiology report findings...">{{ $invoice->report_text }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Report</button>
            </form>
        </div>
    </div>
    @endif

    {{-- Radiology Images --}}
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-images me-2" style="color:var(--accent-secondary);"></i>Radiology Images</span>
            @if($invoice->radiology_images)
                <span class="badge bg-secondary">{{ count($invoice->radiology_images) }} file(s)</span>
            @endif
        </div>
        <div class="card-body">
            {{-- Existing images gallery --}}
            @if($invoice->radiology_images && count($invoice->radiology_images) > 0)
                <div class="row g-3 mb-4">
                    @foreach($invoice->radiology_images as $idx => $imagePath)
                        <div class="col-md-4 col-lg-3">
                            <div class="position-relative rounded overflow-hidden" style="border:1px solid var(--glass-border);">
                                @if(str_ends_with(strtolower($imagePath), '.pdf'))
                                    <a href="{{ Storage::url($imagePath) }}" target="_blank" class="d-flex flex-column align-items-center justify-content-center p-4 text-decoration-none" style="min-height:160px; background:var(--glass-bg);">
                                        <i class="bi bi-file-earmark-pdf" style="font-size:3rem; color:var(--accent-danger);"></i>
                                        <span class="small mt-2" style="color:var(--text-muted);">PDF Document</span>
                                    </a>
                                @else
                                    <a href="#" class="lightbox-trigger" data-src="{{ Storage::url($imagePath) }}" data-caption="Image {{ $idx + 1 }}">
                                        <img src="{{ Storage::url($imagePath) }}" alt="Radiology Image {{ $idx + 1 }}" class="img-fluid w-100" style="min-height:160px; object-fit:cover; cursor:zoom-in;">
                                    </a>
                                @endif
                                @if($invoice->status !== 'completed' && !($invoice->isPaid() && $invoice->isWorkCompleted()))
                                    <form action="{{ route('radiology.invoices.delete-image', [$invoice, $idx]) }}" method="POST" class="position-absolute top-0 end-0 m-1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this image?')" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mb-3" style="color:var(--text-muted);">No images uploaded yet.</p>
            @endif

            {{-- Upload form (only when actively working) --}}
            @if($invoice->status === 'in_progress' || ($invoice->status === 'paid' && $invoice->performed_by_user_id && !$invoice->isWorkCompleted()))
                <form action="{{ route('radiology.invoices.upload-images', $invoice) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="images" class="form-label">Upload Images (JPG, PNG, PDF — max 10MB each)</label>
                        <input type="file" name="images[]" id="images" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf" required>
                        @error('images.*')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-upload me-1"></i>Upload Images</button>
                </form>
            @endif
        </div>
    </div>

    {{-- MedGemma AI Second Opinion --}}
    @php
        $radHasImages = !empty($invoice->radiology_images) && count($invoice->radiology_images) > 0;
        $radHasReport = !empty($invoice->report_text);
        if (!$radHasImages && !$radHasReport) {
            $radReadinessNote = '<strong>Tip:</strong> Upload your imaging files and write your report first, then request AI analysis. MedGemma can analyse images (JPG/PNG) directly.';
        } elseif (!$radHasImages) {
            $radReadinessNote = '<strong>Tip:</strong> Upload imaging files (JPG/PNG) for MedGemma to perform visual analysis alongside your report.';
        } elseif (!$radHasReport) {
            $radReadinessNote = 'Images are ready for analysis. You can also add your report text for MedGemma to verify your findings.';
        } else {
            $radReadinessNote = null;
        }
    @endphp
    @include('components.ai-analysis.card', [
        'analyses' => $aiAnalyses,
        'formAction' => route('ai-analysis.radiology', $invoice),
        'contextLabel' => 'imaging',
        'readinessNote' => $radReadinessNote,
    ])

    {{-- Actions --}}
    <div class="d-flex gap-2 mb-4 fade-in delay-3">
        <a href="{{ route('radiology.invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        @if($invoice->status === 'pending')
            <div class="alert alert-warning mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle"></i>
                <span><strong>Awaiting Payment</strong> — Invoice must be paid by the receptionist before work can begin.</span>
            </div>
        @endif
        @if($invoice->status === 'paid' && !$invoice->performed_by_user_id)
            <form action="{{ route('radiology.invoices.start-work', $invoice) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary" onclick="return confirm('Start work on this imaging order?')"><i class="bi bi-play-circle me-1"></i>Start Work</button>
            </form>
        @endif
        @if($invoice->status === 'paid' && $invoice->performed_by_user_id && $invoice->report_text && !$invoice->isWorkCompleted())
            <form action="{{ route('radiology.invoices.mark-complete', $invoice) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('Mark this imaging order as completed?')"><i class="bi bi-check-circle me-1"></i>Mark as Completed</button>
            </form>
        @endif
    </div>

    {{-- Discount --}}
    @if($invoice->discount_amount > 0 || ($invoice->discount_status ?? 'none') !== 'none')
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-percent me-2" style="color:var(--accent-warning);"></i>Discount</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Status</span>
                    @php $dStyle = match($invoice->discount_status ?? 'none') { 'pending' => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);', 'approved' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);', 'rejected' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);', default => '', }; @endphp
                    <span class="badge-glass" style="{{ $dStyle }}">{{ ucfirst($invoice->discount_status ?? 'none') }}</span>
                </div>
            </div>
            @if($invoice->discount_reason)
                <p class="small mt-2 mb-0" style="color:var(--text-muted);">Reason: {{ $invoice->discount_reason }}</p>
            @endif
        </div>
    </div>
    @endif

    {{-- Discount Request --}}
    @if(!$invoice->isPaid() && $invoice->status !== 'cancelled' && ($invoice->discount_status ?? 'none') !== 'pending')
    <div class="card mb-4 fade-in delay-4">
        <div class="card-header"><i class="bi bi-tag me-2" style="color:var(--accent-info);"></i>Request Discount</div>
        <div class="card-body">
            <form action="{{ route('invoices.discount.request', $invoice) }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-8">
                        <input type="text" name="discount_reason" class="form-control" placeholder="Reason for discount request" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-warning btn-sm w-100" onclick="return confirm('Submit discount request?')"><i class="bi bi-send me-1"></i>Request</button>
                    </div>
                </div>
                <small class="d-block mt-2" style="color:var(--text-muted);"><i class="bi bi-info-circle me-1"></i>The discount amount will be set by the receptionist or owner.</small>
            </form>
        </div>
    </div>
    @endif

    {{-- Performer & Payment --}}
    @if($invoice->performer || $invoice->payer)
    <div class="card mb-4 fade-in delay-5">
        <div class="card-header"><i class="bi bi-people me-2" style="color:var(--accent-secondary);"></i>Processing Info</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
                @if($invoice->performer)
                <div class="info-grid-item">
                    <span class="info-label">Performed by</span>
                    <span class="info-value">{{ $invoice->performer->name }}</span>
                </div>
                @endif
                @if($invoice->payer)
                <div class="info-grid-item">
                    <span class="info-label">Paid by</span>
                    <span class="info-value">{{ $invoice->payer->name }}</span>
                </div>
                @endif
                @if($invoice->paid_at)
                <div class="info-grid-item">
                    <span class="info-label">Paid at</span>
                    <span class="info-value">{{ $invoice->paid_at->format('M d, Y H:i') }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Lightbox --}}
<div id="lightbox" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:9999; align-items:center; justify-content:center; flex-direction:column;">
    <button id="lightbox-close" style="position:absolute; top:18px; right:24px; background:none; border:none; color:#fff; font-size:2rem; cursor:pointer; line-height:1;" title="Close">&times;</button>
    <div style="position:relative; max-width:92vw; max-height:88vh; display:flex; align-items:center; justify-content:center;">
        <img id="lightbox-img" src="" alt="" style="max-width:100%; max-height:85vh; border-radius:6px; object-fit:contain; transition:transform 0.15s ease; transform-origin:center;">
    </div>
    <p id="lightbox-caption" style="color:#aaa; font-size:0.85rem; margin-top:10px;"></p>
    <p style="color:#555; font-size:0.75rem; margin-top:4px;">Scroll to zoom &nbsp;|&nbsp; Click outside to close</p>
</div>

@push('scripts')
<script>
(function () {
    const lb = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');
    const lbCaption = document.getElementById('lightbox-caption');
    let scale = 1;

    function openLightbox(src, caption) {
        scale = 1;
        lbImg.style.transform = 'scale(1)';
        lbImg.src = src;
        lbCaption.textContent = caption || '';
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        lb.style.display = 'none';
        lbImg.src = '';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.lightbox-trigger').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openLightbox(el.dataset.src, el.dataset.caption);
        });
    });

    document.getElementById('lightbox-close').addEventListener('click', closeLightbox);

    lb.addEventListener('click', function (e) {
        if (e.target === lb) closeLightbox();
    });

    document.addEventListener('keydown', function (e) {
        if (lb.style.display !== 'none' && (e.key === 'Escape' || e.key === 'Esc')) closeLightbox();
    });

    lbImg.addEventListener('wheel', function (e) {
        e.preventDefault();
        scale += e.deltaY < 0 ? 0.15 : -0.15;
        scale = Math.min(Math.max(scale, 0.5), 5);
        lbImg.style.transform = 'scale(' + scale + ')';
    }, { passive: false });
}());
</script>
@endpush
@endsection
