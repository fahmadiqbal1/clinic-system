<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\FbrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Owner controller for managing FBR IRIS digital invoicing settings.
 * Handles credential storage, connection testing, and configuration.
 */
class FbrSettingsController extends Controller
{
    /**
     * Save FBR IRIS settings submitted from the owner profile page.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fbr_api_url'          => ['nullable', 'string', 'url', 'max:500'],
            'fbr_bearer_token'     => ['nullable', 'string', 'max:512'],
            'fbr_posid'            => ['nullable', 'string', 'max:50'],
            'fbr_strn'             => ['nullable', 'string', 'max:50'],
            'fbr_ntn'              => ['nullable', 'string', 'max:50'],
            'fbr_business_name'    => ['nullable', 'string', 'max:255'],
            'fbr_business_address' => ['nullable', 'string', 'max:500'],
            'fbr_city'             => ['nullable', 'string', 'max:100'],
            'fbr_tax_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fbr_is_sandbox'       => ['nullable', 'in:1,0'],
        ]);

        $fbr = PlatformSetting::fbr();

        $isSandbox = ($validated['fbr_is_sandbox'] ?? '1') === '1';

        // Update API endpoint based on sandbox toggle
        $defaultUrl = $isSandbox
            ? 'https://sdnfbr.fbr.gov.pk/invoices/v1'
            : 'https://gst.fbr.gov.pk/invoices/v1';

        $data = [
            'api_url' => $validated['fbr_api_url'] ?? $defaultUrl,
            'meta'    => array_merge($fbr->meta ?? [], [
                'posid'            => $validated['fbr_posid'] ?? $fbr->getMeta('posid'),
                'strn'             => $validated['fbr_strn'] ?? $fbr->getMeta('strn'),
                'ntn'              => $validated['fbr_ntn'] ?? $fbr->getMeta('ntn'),
                'business_name'    => $validated['fbr_business_name'] ?? $fbr->getMeta('business_name'),
                'business_address' => $validated['fbr_business_address'] ?? $fbr->getMeta('business_address'),
                'city'             => $validated['fbr_city'] ?? $fbr->getMeta('city'),
                'tax_rate'         => $validated['fbr_tax_rate'] ?? $fbr->getMeta('tax_rate', 0),
                'is_sandbox'       => $isSandbox,
            ]),
        ];

        // Only update the bearer token if a non-empty value was submitted
        if (!empty($validated['fbr_bearer_token'])) {
            $data['api_key'] = $validated['fbr_bearer_token'];
            $data['status']  = 'disconnected';
            $data['last_error'] = null;
        }

        $fbr->update($data);

        return back()->with('success', 'FBR settings saved. Test the connection to verify your credentials.');
    }

    /**
     * Test the FBR IRIS API connection.
     * Returns JSON for the live status badge update in the owner profile page.
     */
    public function testConnection(): JsonResponse
    {
        $fbr    = PlatformSetting::fbr();
        $result = (new FbrService($fbr))->testConnection();

        return response()->json($result);
    }
}
