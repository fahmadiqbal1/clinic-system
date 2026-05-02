@if($items->count() > 0)
    <div class="glass-card fade-in delay-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        @if(!$userDepartment)
                        <th>Department</th>
                        @endif
                        <th>SKU / Barcode</th>
                        <th>Unit</th>
                        <th class="text-end">Stock</th>
                        <th class="text-end">Min Level</th>
                        @if(Auth::user()->hasRole('Owner'))
                        <th class="text-end">Purchase {{ currency_symbol() }}</th>
                        @endif
                        <th class="text-end">Selling {{ currency_symbol() }}</th>
                        <th class="text-center">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $item->name }}</span>
                                @if($item->manufacturer_tag)
                                    @php
                                        // Deterministic colour from tag string (one of 6 accent colours)
                                        $tagColors = ['var(--accent-primary)','var(--accent-success)','var(--accent-info)','var(--accent-warning)','#8b5cf6','#ec4899'];
                                        $tagColor  = $tagColors[crc32($item->manufacturer_tag) % 6];
                                    @endphp
                                    <span style="font-size:0.6rem;background:{{ $tagColor }};color:#fff;border-radius:3px;padding:1px 5px;vertical-align:middle;margin-left:4px;">{{ $item->manufacturer_tag }}</span>
                                @endif
                                @if($item->chemical_formula)
                                    <br><small style="color:var(--text-muted);">{{ $item->chemical_formula }}</small>
                                @endif
                                @if($item->manufacturer && !$item->manufacturer_tag)
                                    <br><small style="color:var(--text-muted);">{{ $item->manufacturer }}</small>
                                @endif
                                @if($item->requires_prescription)
                                    <span class="badge-glass ms-1" style="background:rgba(var(--accent-warning-rgb),0.18);color:var(--accent-warning);font-size:0.65rem;">Rx</span>
                                @endif
                            </td>
                            @if(!$userDepartment)
                            <td><span class="badge-glass">{{ ucfirst($item->department) }}</span></td>
                            @endif
                            <td>
                                <span style="color:var(--text-muted);">{{ $item->sku ?? '—' }}</span>
                                @if($item->barcode)
                                    <br><small style="color:var(--text-muted);"><i class="bi bi-upc me-1"></i>{{ $item->barcode }}</small>
                                @endif
                            </td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end">
                                @php
                                    $stock   = $item->current_stock;
                                    $minimum = $item->minimum_stock_level ?? 0;
                                @endphp
                                @if($stock <= 0)
                                    <span style="color:var(--accent-danger);" class="fw-bold" title="Out of stock">0</span>
                                    <span class="ms-1" style="font-size:0.65rem;background:var(--accent-danger);color:#fff;border-radius:3px;padding:1px 4px;">OUT</span>
                                @elseif($stock <= $minimum)
                                    <span style="color:var(--accent-danger);" class="fw-bold" title="Below minimum stock level">{{ $stock }}</span>
                                    <span class="ms-1" style="font-size:0.65rem;background:var(--accent-danger);color:#fff;border-radius:3px;padding:1px 4px;">LOW</span>
                                @elseif($minimum > 0 && $stock <= $minimum * 1.5)
                                    <span style="color:var(--accent-warning);" class="fw-medium" title="Approaching minimum stock level">{{ $stock }}</span>
                                    <span class="ms-1" style="font-size:0.65rem;background:var(--accent-warning);color:#000;border-radius:3px;padding:1px 4px;">⚠</span>
                                @else
                                    <span class="fw-medium">{{ $stock }}</span>
                                @endif
                            </td>
                            <td class="text-end" style="color:var(--text-muted);">{{ $item->minimum_stock_level }}</td>
                            @if(Auth::user()->hasRole('Owner'))
                            <td class="text-end">{{ currency($item->purchase_price) }}</td>
                            @endif
                            <td class="text-end">
                                <span class="btn-quick-price" role="button" style="cursor:pointer;text-decoration:underline dotted;text-underline-offset:3px;"
                                      data-item-id="{{ $item->id }}" data-current-price="{{ $item->selling_price }}"
                                      title="Click to edit price">{{ currency($item->selling_price) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="btn-toggle-active badge-glass" role="button" style="cursor:pointer;"
                                      data-item-id="{{ $item->id }}" data-is-active="{{ $item->is_active ? '1' : '0' }}"
                                      title="Click to toggle">
                                    @if($item->is_active)
                                        <span style="background:rgba(var(--accent-success-rgb),0.18);color:var(--accent-success);padding:2px 8px;border-radius:6px;">Active</span>
                                    @else
                                        <span style="background:rgba(100,100,100,0.18);color:var(--text-muted);padding:2px 8px;border-radius:6px;">Inactive</span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('inventory.edit', $item) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="{{ route('inventory.adjust', $item) }}" class="btn btn-sm btn-outline-warning" title="Stock Adjust"><i class="bi bi-plus-slash-minus"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            {{ $items->links() }}
        </div>
    </div>
@else
    <div class="empty-state fade-in delay-1">
        <i class="bi bi-box-seam" style="font-size:2.5rem;opacity:0.3;"></i>
        <h6 class="mt-3 mb-1">No inventory items found</h6>
        <p class="small mb-0" style="color:var(--text-muted);">Adjust your filters or add a new item.</p>
    </div>
@endif
