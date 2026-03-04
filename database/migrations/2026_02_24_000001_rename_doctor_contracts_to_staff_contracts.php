<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite we need to recreate the table since it doesn't support rename column well
        Schema::create('staff_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('version')->default(1);
            $table->longText('contract_html_snapshot');
            $table->integer('minimum_term_months')->default(12);
            $table->date('effective_from');
            $table->string('status')->default('draft'); // draft, active, superseded
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip')->nullable();
            $table->text('signed_user_agent')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('resignation_notice_submitted_at')->nullable();
            $table->boolean('early_exit_flag')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->index('user_id');
            $table->index('status');
            $table->unique(['user_id', 'version']);
        });

        // Migrate existing data from doctor_contracts to staff_contracts
        if (Schema::hasTable('doctor_contracts')) {
            $rows = DB::table('doctor_contracts')->get();
            foreach ($rows as $row) {
                DB::table('staff_contracts')->insert([
                    'id' => $row->id,
                    'user_id' => $row->doctor_id,
                    'version' => $row->version,
                    'contract_html_snapshot' => $row->contract_html_snapshot,
                    'minimum_term_months' => $row->minimum_term_months,
                    'effective_from' => $row->effective_from,
                    'status' => $row->status,
                    'signed_at' => $row->signed_at,
                    'signed_ip' => $row->signed_ip,
                    'signed_user_agent' => $row->signed_user_agent,
                    'created_by' => $row->created_by,
                    'resignation_notice_submitted_at' => $row->resignation_notice_submitted_at,
                    'early_exit_flag' => $row->early_exit_flag,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            Schema::dropIfExists('doctor_contracts');
        }
    }

    public function down(): void
    {
        Schema::create('doctor_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->integer('version')->default(1);
            $table->longText('contract_html_snapshot');
            $table->integer('minimum_term_months')->default(12);
            $table->date('effective_from');
            $table->string('status')->default('draft');
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip')->nullable();
            $table->text('signed_user_agent')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('resignation_notice_submitted_at')->nullable();
            $table->boolean('early_exit_flag')->default(false);
            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->index('doctor_id');
            $table->index('status');
            $table->unique(['doctor_id', 'version']);
        });

        if (Schema::hasTable('staff_contracts')) {
            $rows = DB::table('staff_contracts')->get();
            foreach ($rows as $row) {
                DB::table('doctor_contracts')->insert([
                    'id' => $row->id,
                    'doctor_id' => $row->user_id,
                    'version' => $row->version,
                    'contract_html_snapshot' => $row->contract_html_snapshot,
                    'minimum_term_months' => $row->minimum_term_months,
                    'effective_from' => $row->effective_from,
                    'status' => $row->status,
                    'signed_at' => $row->signed_at,
                    'signed_ip' => $row->signed_ip,
                    'signed_user_agent' => $row->signed_user_agent,
                    'created_by' => $row->created_by,
                    'resignation_notice_submitted_at' => $row->resignation_notice_submitted_at,
                    'early_exit_flag' => $row->early_exit_flag,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            Schema::dropIfExists('staff_contracts');
        }
    }
};
