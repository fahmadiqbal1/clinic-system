<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorPriceList;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VendorDashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $vendor = Vendor::where('vendor_user_id', $user->id)->firstOrFail();

        $recentPriceLists = VendorPriceList::where('vendor_id', $vendor->id)
            ->latest()
            ->limit(5)
            ->get();

        $pendingCount  = VendorPriceList::where('vendor_id', $vendor->id)->whereIn('status', ['pending', 'processing'])->count();
        $appliedCount  = VendorPriceList::where('vendor_id', $vendor->id)->where('status', 'applied')->count();
        $flaggedCount  = VendorPriceList::where('vendor_id', $vendor->id)->where('status', 'flagged')->count();

        return view('vendor.dashboard', compact('vendor', 'recentPriceLists', 'pendingCount', 'appliedCount', 'flaggedCount'));
    }
}
