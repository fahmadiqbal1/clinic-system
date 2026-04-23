<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Owner-only gateway to the NocoBase admin UI.
 * Spatie role:Owner middleware enforced at the route level.
 * SSO: not implemented — NocoBase auth plugin does not support
 * signed-cookie handoff from PHP sessions. Owner logs in separately.
 */
class NocobaseController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $enabled  = PlatformSetting::isEnabled('admin.nocobase.enabled');
        $nocobaseUrl = rtrim(config('clinic.nocobase_url', 'http://localhost:13000'), '/');

        return view('owner.nocobase', compact('enabled', 'nocobaseUrl'));
    }
}
