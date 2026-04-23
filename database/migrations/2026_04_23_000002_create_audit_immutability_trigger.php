<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill hash chain for all existing rows before the trigger locks the table.
        $prevHash = '';
        DB::table('audit_logs')->orderBy('id')->chunkById(500, function ($rows) use (&$prevHash) {
            foreach ($rows as $row) {
                $canonical = json_encode([
                    'user_id'        => $row->user_id,
                    'action'         => $row->action,
                    'auditable_type' => $row->auditable_type,
                    'auditable_id'   => $row->auditable_id,
                    'before_state'   => $row->before_state,
                    'after_state'    => $row->after_state,
                    'ip_address'     => $row->ip_address,
                    'user_agent'     => $row->user_agent ?? null,
                    'session_id'     => $row->session_id ?? null,
                    'created_at'     => $row->created_at,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $rowHash = hash('sha256', $prevHash . '|' . $canonical);

                DB::table('audit_logs')
                    ->where('id', $row->id)
                    ->update(['prev_hash' => $prevHash, 'row_hash' => $rowHash]);

                $prevHash = $rowHash;
            }
        });

        // Trigger: block all UPDATE operations on audit_logs.
        DB::unprepared('
            CREATE TRIGGER audit_logs_prevent_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE \'45000\'
                SET MESSAGE_TEXT = \'audit_logs is append-only: UPDATE is not permitted\';
            END
        ');

        // Trigger: block all DELETE operations on audit_logs.
        DB::unprepared('
            CREATE TRIGGER audit_logs_prevent_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE \'45000\'
                SET MESSAGE_TEXT = \'audit_logs is append-only: DELETE is not permitted\';
            END
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');
    }
};
