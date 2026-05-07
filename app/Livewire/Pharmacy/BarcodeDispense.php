<?php

namespace App\Livewire\Pharmacy;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class BarcodeDispense extends Component
{
    public string $barcode = '';

    public ?InventoryItem $item = null;

    #[Validate('required|integer|min:1')]
    public int $quantity = 1;

    public string $message = '';

    public string $messageType = 'info';

    /** Current stock level for the scanned item. */
    public int $currentStock = 0;

    public function scanBarcode(): void
    {
        $this->message = '';
        $this->item = null;
        $this->currentStock = 0;

        $barcode = trim($this->barcode);

        if ($barcode === '') {
            return;
        }

        // InventoryItem has both `barcode` and `sku` columns — try barcode first, then sku
        $item = InventoryItem::where('barcode', $barcode)
            ->orWhere('sku', $barcode)
            ->where('is_active', true)
            ->first();

        if (! $item) {
            $this->message = "No active item found for barcode / SKU: {$barcode}";
            $this->messageType = 'danger';
            return;
        }

        $this->item = $item;
        $this->currentStock = $this->resolveCurrentStock($item);
        $this->quantity = 1;
    }

    public function dispense(InventoryService $inventoryService): void
    {
        if (! $this->item) {
            $this->message = 'No item scanned. Please scan a barcode first.';
            $this->messageType = 'warning';
            return;
        }

        $this->validate();

        try {
            $inventoryService->recordOutbound(
                item: $this->item,
                quantity: $this->quantity,
                referenceType: 'manual_dispense',
                referenceId: $this->item->id,
                user: Auth::user(),
            );

            $this->message = "Dispensed {$this->quantity} × {$this->item->name} successfully.";
            $this->messageType = 'success';

            // Refresh stock after dispense
            $this->item->refresh();
            $this->currentStock = $this->resolveCurrentStock($this->item);
        } catch (\Exception $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'danger';
        }
    }

    public function resetScan(): void
    {
        $this->barcode = '';
        $this->item = null;
        $this->quantity = 1;
        $this->message = '';
        $this->messageType = 'info';
        $this->currentStock = 0;
    }

    public function render()
    {
        return view('livewire.pharmacy.barcode-dispense');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveCurrentStock(InventoryItem $item): int
    {
        // Stock = sum of all movements (positive for inbound, negative for outbound)
        return (int) StockMovement::where('inventory_item_id', $item->id)
            ->sum('quantity');
    }
}
