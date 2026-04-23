<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\View\View;

class ArchitectureController extends Controller
{
    public function index(): View
    {
        $enabled = PlatformSetting::isEnabled('ai.gitnexus.enabled');

        $graphFile = storage_path('gitnexus/graph.json');
        $graph     = null;
        $meta      = null;

        if (file_exists($graphFile)) {
            $raw   = json_decode(file_get_contents($graphFile), true);
            $meta  = $raw['meta'] ?? null;
            $graph = $raw['elements'] ?? null;
        }

        return view('owner.architecture', compact('enabled', 'meta', 'graph'));
    }
}
