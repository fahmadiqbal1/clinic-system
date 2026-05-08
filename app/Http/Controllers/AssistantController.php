<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Services\AiSidecarClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AssistantController extends Controller
{
    public function __construct(private readonly AiSidecarClient $sidecar) {}

    public function chat(Request $request): JsonResponse
    {
        if (! PlatformSetting::isEnabled('ai.sidecar.enabled')) {
            return response()->json([
                'reply'               => 'The AI assistant is currently disabled. Ask the owner to enable it in Platform Settings.',
                'action'              => null,
                'clarifying_question' => null,
            ]);
        }

        $request->validate([
            'message'      => 'nullable|string|max:1000',
            'current_page' => 'nullable|string|max:255',
            'session_id'   => 'nullable|string|max:64',
            'file'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,csv|max:20480',
        ]);

        $user = Auth::user();
        $role = $user?->getRoleNames()->first() ?? 'User';

        $payload = [
            'message'      => $request->input('message', ''),
            'role'         => $role,
            'current_page' => $request->input('current_page', '/'),
            'session_id'   => $request->input('session_id', bin2hex(random_bytes(16))),
        ];

        $fileContents = null;
        $fileName     = null;
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file         = $request->file('file');
            $fileContents = file_get_contents($file->getRealPath());
            $fileName     = $file->getClientOriginalName();
        }

        try {
            $result = $this->sidecar->assistantChat($payload, $fileContents, $fileName);
            return response()->json($result);
        } catch (\Throwable $e) {
            $raw = $e->getMessage();
            $msg = match (true) {
                str_contains($raw, 'circuit open')    => 'AI assistant is temporarily unavailable. Please try again in a moment.',
                str_contains($raw, 'refused')         => 'AI assistant is not reachable. Check that the sidecar is running.',
                str_contains($raw, 'timed out')       => 'AI assistant timed out. Please try again.',
                default                               => 'AI assistant error: ' . Str::limit($raw, 100),
            };

            return response()->json([
                'reply'               => $msg,
                'action'              => null,
                'clarifying_question' => null,
            ], 503);
        }
    }
}
