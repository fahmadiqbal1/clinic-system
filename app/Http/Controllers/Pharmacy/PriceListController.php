<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class PriceListController extends Controller
{
    public function create(): View
    {
        return view('pharmacy.price-list.upload');
    }

    /**
     * Parse a CSV price list upload, diff against current prices,
     * and create a ProcurementRequest of type price_list for owner approval.
     *
     * Expected CSV columns (flexible):  sku, name, price   (header row required)
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'price_list' => 'required|file|mimes:csv,txt|max:5120',
            'notes'      => 'nullable|string|max:500',
        ]);

        $path = $request->file('price_list')->store('price-lists', 'local');
        $fullPath = storage_path('app/' . $path);

        $rows = array_map('str_getcsv', file($fullPath));
        if (empty($rows)) {
            return back()->with('error', 'CSV file is empty.');
        }

        $header = array_map('strtolower', array_map('trim', $rows[0]));
        $skuIdx   = array_search('sku', $header);
        $nameIdx  = array_search('name', $header);
        $priceIdx = array_search('price', $header);

        if ($priceIdx === false) {
            return back()->with('error', 'CSV must have a "price" column header.');
        }

        $diff = [];
        $changed = 0;

        foreach (array_slice($rows, 1) as $row) {
            if (count($row) <= $priceIdx) continue;
            $newPrice = (float) str_replace(',', '', trim($row[$priceIdx] ?? ''));
            if ($newPrice <= 0) continue;

            $item = null;
            if ($skuIdx !== false && !empty($row[$skuIdx])) {
                $item = InventoryItem::where('sku', trim($row[$skuIdx]))->first();
            }
            if (!$item && $nameIdx !== false && !empty($row[$nameIdx])) {
                $item = InventoryItem::where('name', trim($row[$nameIdx]))->first();
            }
            if (!$item) continue;

            $oldPrice = (float) $item->selling_price;
            if (abs($oldPrice - $newPrice) < 0.01) continue;

            $diff[] = [
                'id'        => $item->id,
                'sku'       => $item->sku,
                'name'      => $item->name,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
            ];
            $changed++;
        }

        if ($changed === 0) {
            return back()->with('success', 'No price changes detected — all prices are already up to date.');
        }

        ProcurementRequest::create([
            'department'       => 'pharmacy',
            'type'             => ProcurementRequest::TYPE_PRICE_LIST,
            'requested_by'     => Auth::id(),
            'status'           => 'pending',
            'notes'            => $request->notes ?? "Price list upload — {$changed} items changed.",
            'price_list_path'  => $path,
            'price_list_diff'  => $diff,
        ]);

        return redirect()->route('pharmacy.dashboard')
            ->with('success', "{$changed} price change(s) submitted for owner approval.");
    }
}
