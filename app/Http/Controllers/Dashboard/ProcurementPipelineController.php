<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Queries\ProcurementPipelineQueryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProcurementPipelineController extends Controller
{
    use AuthorizesRequests;

    protected ProcurementPipelineQueryService $pipeline;

    public function __construct(ProcurementPipelineQueryService $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Display procurement pipeline dashboard
     */
    public function index()
    {
        $this->authorize('viewProcurementPipeline');

        $summary = $this->pipeline->getPipelineSummary();
        $pendingInventory = $this->pipeline->getPendingInventory();
        $pendingService = $this->pipeline->getPendingService();
        $approvedInventory = $this->pipeline->getApprovedInventory();
        $approvedService = $this->pipeline->getApprovedService();
        $received = $this->pipeline->getReceived();

        return view('dashboard.procurement-pipeline', [
            'summary' => $summary,
            'pendingInventory' => $pendingInventory,
            'pendingService' => $pendingService,
            'approvedInventory' => $approvedInventory,
            'approvedService' => $approvedService,
            'received' => $received,
        ]);
    }
}
