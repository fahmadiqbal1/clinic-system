<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RetentionPolicyController extends Controller
{
    private const TYPES = [
        'clinical'  => ['label' => 'Clinical Events',   'default_years' => null],
        'financial' => ['label' => 'Financial Events',  'default_years' => 7],
        'ai'        => ['label' => 'AI Invocations',    'default_years' => 2],
    ];

    public function index(): View
    {
        $policies = [];

        foreach (self::TYPES as $key => $def) {
            $row = PlatformSetting::where('platform_name', "retention.{$key}")
                ->where('provider', 'retention_policy')
                ->first();

            $policies[$key] = [
                'label'   => $def['label'],
                'years'   => $row ? $row->getMeta('years') : $def['default_years'],
                'default' => $def['default_years'],
            ];
        }

        return view('owner.retention-policy', compact('policies'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clinical_years'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'financial_years' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ai_years'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        foreach (array_keys(self::TYPES) as $key) {
            $years = isset($validated["{$key}_years"]) ? (int) $validated["{$key}_years"] : null;

            PlatformSetting::updateOrCreate(
                ['platform_name' => "retention.{$key}", 'provider' => 'retention_policy'],
                ['meta' => ['years' => $years]]
            );
        }

        return back()->with('success', 'Retention policy saved.');
    }
}
