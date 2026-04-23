<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AiInvocation extends Model
{
    protected $table = 'ai_invocations';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'case_token', 'endpoint', 'prompt_hash',
        'retrieval_doc_ids', 'model_id', 'latency_ms', 'outcome',
        'prev_hash', 'row_hash', 'created_at',
    ];

    protected $casts = [
        'retrieval_doc_ids' => 'json',
        'created_at'        => 'datetime',
    ];

    /**
     * Hash-chained invocation log — mirrors audit_logs chain semantics.
     * Field order in canonicalJson is fixed; changing it invalidates the chain.
     */
    public static function log(
        string $endpoint,
        string $promptHash,
        int $latencyMs,
        string $outcome,
        ?string $caseToken = null,
        ?string $modelId = null,
        ?array $retrievalDocIds = null,
    ): self {
        $userId    = Auth::id();
        $createdAt = now()->format('Y-m-d H:i:s');

        return DB::transaction(function () use (
            $endpoint, $promptHash, $latencyMs, $outcome,
            $caseToken, $modelId, $retrievalDocIds, $userId, $createdAt
        ) {
            $prevHash = (string) (static::lockForUpdate()->latest('id')->value('row_hash') ?? '');

            $data = [
                'user_id'           => $userId,
                'case_token'        => $caseToken,
                'endpoint'          => $endpoint,
                'prompt_hash'       => $promptHash,
                'retrieval_doc_ids' => $retrievalDocIds,
                'model_id'          => $modelId,
                'latency_ms'        => $latencyMs,
                'outcome'           => $outcome,
                'prev_hash'         => $prevHash,
                'created_at'        => $createdAt,
            ];

            $data['row_hash'] = hash('sha256', $prevHash . '|' . static::canonicalJson($data));

            return static::create($data);
        });
    }

    public static function canonicalJson(array $data): string
    {
        return json_encode([
            'user_id'           => $data['user_id'],
            'case_token'        => $data['case_token'],
            'endpoint'          => $data['endpoint'],
            'prompt_hash'       => $data['prompt_hash'],
            'retrieval_doc_ids' => $data['retrieval_doc_ids'],
            'model_id'          => $data['model_id'],
            'latency_ms'        => $data['latency_ms'],
            'outcome'           => $data['outcome'],
            'created_at'        => $data['created_at'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
