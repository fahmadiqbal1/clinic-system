<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\FbrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Owner controller for managing FBR PRAL Digital Invoicing (DI) settings.
 * Handles credential storage, connection testing, and configuration.
 */
class FbrSettingsController extends Controller
{
    /**
     * Save FBR DI settings submitted from the owner profile page.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fbr_ntn'                => ['nullable', 'string', 'max:50'],
            'fbr_strn'               => ['nullable', 'string', 'max:50'],
            'fbr_business_name'      => ['nullable', 'string', 'max:255'],
            'fbr_business_address'   => ['nullable', 'string', 'max:500'],
            'fbr_seller_province'    => ['nullable', 'string', 'max:100'],
            'fbr_sale_type'          => ['nullable', 'string', 'max:100'],
            'fbr_uom'                => ['nullable', 'string', 'max:100'],
            'fbr_tax_rate'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fbr_is_sandbox'         => ['nullable', 'in:1,0'],
            'fbr_sandbox_api_key'    => ['nullable', 'string', 'max:512'],
            'fbr_production_api_key' => ['nullable', 'string', 'max:512'],
            'fbr_signing_secret'     => ['nullable', 'string', 'max:255'],
        ]);

        $fbr       = PlatformSetting::fbr();
        $isSandbox = ($validated['fbr_is_sandbox'] ?? '1') === '1';

        $newMeta = array_merge($fbr->meta ?? [], [
            'ntn'               => $validated['fbr_ntn']              ?? $fbr->getMeta('ntn'),
            'strn'              => $validated['fbr_strn']             ?? $fbr->getMeta('strn'),
            'business_name'     => $validated['fbr_business_name']    ?? $fbr->getMeta('business_name'),
            'business_address'  => $validated['fbr_business_address'] ?? $fbr->getMeta('business_address'),
            'seller_province'   => $validated['fbr_seller_province']  ?? $fbr->getMeta('seller_province', 'Punjab'),
            'sale_type'         => $validated['fbr_sale_type']        ?? $fbr->getMeta('sale_type', 'Services'),
            'uom'               => $validated['fbr_uom']              ?? $fbr->getMeta('uom', 'Numbers, pieces, units'),
            'tax_rate'          => $validated['fbr_tax_rate']         ?? $fbr->getMeta('tax_rate', 0),
            'is_sandbox'        => $isSandbox,
        ]);

        // Only update tokens when non-empty values are submitted (avoid clearing on partial save)
        if (!empty($validated['fbr_sandbox_api_key'])) {
            $newMeta['sandbox_api_key'] = $validated['fbr_sandbox_api_key'];
        }
        if (!empty($validated['fbr_production_api_key'])) {
            $newMeta['production_api_key'] = $validated['fbr_production_api_key'];
        }
        if (!empty($validated['fbr_signing_secret'])) {
            $newMeta['signing_secret'] = $validated['fbr_signing_secret'];
        }

        $fbr->update([
            'api_url'    => $isSandbox
                ? 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb'
                : 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata',
            'meta'       => $newMeta,
            'status'     => 'disconnected',
            'last_error' => null,
        ]);

        return back()->with('success', 'FBR DI settings saved. Click "Test Connection" to verify your token and IP whitelist.');
    }

    /**
     * Test the FBR PRAL DI API connection using the sandbox validate endpoint.
     * Returns JSON for the live status badge in the owner profile page.
     */
    public function testConnection(): JsonResponse
    {
        $fbr    = PlatformSetting::fbr();
        $result = (new FbrService($fbr))->testConnection();

        return response()->json($result);
    }
}

