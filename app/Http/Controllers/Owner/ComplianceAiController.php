<?php

namespace App\Http\Controllers\Owner;

use App\Events\AiCriticalAlert;
use App\Http\Controllers\Controller;
use App\Models\AiActionRequest;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AiSidecarClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ComplianceAiController extends Controller
{
    public function __construct(private readonly AiSidecarClient $sidecar) {}

    public function index(): View
    {
        abort_unless(PlatformSetting::isEnabled('ai.compliance.enabled'), 404);
        return view('owner.compliance-ai', [
            'title' => 'Compliance AI',
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        if (!PlatformSetting::isEnabled('ai.compliance.enabled')) {
            return response()->json(['error' => 'Compliance AI is disabled.'], 403);
        }

        $validated = $request->validate([
            'scope'           => ['sometimes', 'string', 'in:audit_chain,phi_access,evidence_gap,flag_snapshot,full'],
            'period_days'     => ['sometimes', 'integer', 'min:1', 'max:365'],
            'custom_question' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'session_token'   => bin2hex(random_bytes(32)),
            'scope'           => $validated['scope'] ?? 'full',
            'period_days'     => $validated['period_days'] ?? 30,
            'custom_question' => $validated['custom_question'] ?? null,
        ];

        try {
            $result = $this->sidecar->complianceAnalyse($payload);
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), 'circuit open')
                ? 'Compliance AI temporarily unavailable.'
                : 'Compliance AI error: ' . $e->getMessage();
            return response()->json(['error' => $msg], 503);
        }

        // Hard-rule escalation: any escalation_pending flag persists to audit-trail.
        if (!empty($result['escalation_pending']) || ($result['status'] ?? null) === 'NON_COMPLIANT') {
            AiActionRequest::create([
                'case_token'          => null,
                'requested_by_source' => 'compliance_ai',
                'target_type'         => 'ComplianceFinding',
                'target_id'           => 0,
                'proposed_action'     => 'owner_review',
                'proposed_payload'    => [
                    'status'         => $result['status'] ?? null,
                    'evidence_refs'  => $result['evidence_refs'] ?? [],
                    'rationale'      => mb_substr($result['rationale'] ?? '', 0, 2000),
                    'flagged_by_user_id' => Auth::id(),
                ],
                'status' => 'pending',
            ]);

            // Broadcast real-time alert to all Owner sessions via Reverb.
            $statusLabel = ($result['status'] ?? null) === 'NON_COMPLIANT' ? 'NON-COMPLIANT' : 'Escalation Pending';
            $owners = User::role('Owner')->get();
            foreach ($owners as $owner) {
                broadcast(new AiCriticalAlert(
                    ownerId: $owner->id,
                    title:   "Compliance Alert — {$statusLabel}",
                    message: mb_substr($result['rationale'] ?? 'Review required.', 0, 200),
                    icon:    'bi-shield-exclamation',
                    color:   'danger',
                    url:     '/owner/compliance-ai',
                ))->toOthers();
            }
        }

        return response()->json($result);
    }
}
