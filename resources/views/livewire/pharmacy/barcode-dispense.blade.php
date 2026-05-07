<div
    class="card hover-lift fade-in"
    x-data="{
        buffer: '',
        lastKeyTime: 0,
        init() {
            window.addEventListener('keydown', (e) => {
                const now = Date.now();
                // Barcode scanners send keys very fast; reset buffer if gap > 100ms
                if (now - this.lastKeyTime > 100) {
                    this.buffer = '';
                }
                this.lastKeyTime = now;

                if (e.key === 'Enter') {
                    if (this.buffer.length > 0) {
                        $wire.set('barcode', this.buffer);
                        $wire.scanBarcode();
                        this.buffer = '';
                    }
                } else if (e.key.length === 1) {
                    // Only printable characters
                    this.buffer += e.key;
                }
            });
        }
    }"
>
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-upc-scan" style="color:var(--accent-info);"></i>
        <strong>Barcode Dispense</strong>
    </div>

    <div class="card-body">
        {{-- Manual barcode input (fallback when no physical scanner) --}}
        <div class="mb-3">
            <label for="barcode-input" class="form-label small text-muted">Barcode / SKU</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-upc"></i></span>
                <input
                    id="barcode-input"
                    type="text"
                    class="form-control"
                    wire:model.defer="barcode"
                    placeholder="Scan or type barcode / SKU…"
                    autocomplete="off"
                >
                <button
                    class="btn btn-outline-primary"
                    type="button"
                    wire:click="scanBarcode"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading wire:target="scanBarcode" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i class="bi bi-search" wire:loading.remove wire:target="scanBarcode"></i>
                    Lookup
                </button>
            </div>
            <div class="form-text text-muted"><i class="bi bi-info-circle me-1"></i>Physical scanners are detected automatically.</div>
        </div>

        {{-- Scanned item details --}}
        @if ($item)
            <div class="card border-0 mb-3" style="background:rgba(var(--accent-info-rgb),0.08);">
                <div class="card-body py-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-sm-6">
                            <div class="fw-semibold mb-1">
                                <i class="bi bi-capsule me-1" style="color:var(--accent-success);"></i>
                                {{ $item->name }}
                            </div>
                            <div class="small text-muted">
                                {{ $item->manufacturer }}
                                @if($item->chemical_formula)
                                    &middot; {{ $item->chemical_formula }}
                                @endif
                            </div>
                            <div class="small text-muted">
                                SKU: <code>{{ $item->sku }}</code>
                                @if($item->barcode)
                                    &nbsp;|&nbsp; Barcode: <code>{{ $item->barcode }}</code>
                                @endif
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 text-center">
                            <div class="stat-value {{ $currentStock <= ($item->minimum_stock_level ?? 0) ? 'glow-warning text-warning' : 'glow-success' }}" style="font-size:1.5rem;">
                                {{ $currentStock }}
                            </div>
                            <div class="stat-label small">In Stock</div>
                        </div>
                        <div class="col-6 col-sm-3 text-center">
                            @if($item->last_stocked_at)
                                <div class="small text-muted">Last stocked</div>
                                <div class="small fw-semibold">{{ $item->last_stocked_at->diffForHumans() }}</div>
                            @endif
                            @if($item->unit)
                                <div class="small text-muted mt-1">Unit: {{ $item->unit }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dispense form --}}
            <div class="row g-2 align-items-end mb-2">
                <div class="col-5 col-sm-4">
                    <label for="qty-input" class="form-label small text-muted">Quantity</label>
                    <input
                        id="qty-input"
                        type="number"
                        class="form-control"
                        wire:model.defer="quantity"
                        min="1"
                        max="{{ $currentStock }}"
                    >
                </div>
                <div class="col-7 col-sm-8 d-flex gap-2">
                    <button
                        class="btn btn-success flex-grow-1"
                        wire:click="dispense"
                        wire:loading.attr="disabled"
                        @if($currentStock <= 0) disabled @endif
                    >
                        <span wire:loading wire:target="dispense" class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <i class="bi bi-bag-check me-1" wire:loading.remove wire:target="dispense"></i>
                        Dispense
                    </button>
                    <button class="btn btn-outline-secondary" wire:click="resetScan" title="Clear">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
            </div>
        @endif

        {{-- Message area --}}
        @if ($message)
            <div class="alert alert-{{ $messageType }} alert-dismissible py-2 mb-0 mt-2 fade show" role="alert">
                <i class="bi bi-{{ $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-triangle' : 'info-circle') }} me-1"></i>
                {{ $message }}
                <button type="button" class="btn-close btn-sm" wire:click="$set('message', '')" aria-label="Close"></button>
            </div>
        @endif
    </div>
</div>
