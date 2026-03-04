<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update invoices table for state machine
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                // Change status enum if it exists and needs updating
                if (Schema::hasColumn('invoices', 'status')) {
                    $table->string('status')->change(); // Change to string for flexibility
                } else {
                    $table->string('status')->default('pending');
                }
                
                // Add report text if not exists
                if (!Schema::hasColumn('invoices', 'report_text')) {
                    $table->longText('report_text')->nullable();
                }
                
                // Add has_prescribed_items if not exists
                if (!Schema::hasColumn('invoices', 'has_prescribed_items')) {
                    $table->boolean('has_prescribed_items')->default(false);
                }
            });
        }

        // Update patients table for workflow tracking  
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                // Add consultation notes if not exists
                if (!Schema::hasColumn('patients', 'consultation_notes')) {
                    $table->longText('consultation_notes')->nullable();
                }

                // Add status tracking timestamps if not exists
                if (!Schema::hasColumn('patients', 'status')) {
                    $table->string('status')->default('registered');
                }
                if (!Schema::hasColumn('patients', 'registered_at')) {
                    $table->timestamp('registered_at')->nullable();
                }
                if (!Schema::hasColumn('patients', 'triage_started_at')) {
                    $table->timestamp('triage_started_at')->nullable();
                }
                if (!Schema::hasColumn('patients', 'doctor_started_at')) {
                    $table->timestamp('doctor_started_at')->nullable();
                }
                if (!Schema::hasColumn('patients', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
            });
        }

        // Add is_active to users if not exists
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('email_verified_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'report_text')) {
                    $table->dropColumn('report_text');
                }
                if (Schema::hasColumn('invoices', 'has_prescribed_items')) {
                    $table->dropColumn('has_prescribed_items');
                }
            });
        }

        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (Schema::hasColumn('patients', 'consultation_notes')) {
                    $table->dropColumn('consultation_notes');
                }
                if (Schema::hasColumn('patients', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('patients', 'registered_at')) {
                    $table->dropColumn('registered_at');
                }
                if (Schema::hasColumn('patients', 'triage_started_at')) {
                    $table->dropColumn('triage_started_at');
                }
                if (Schema::hasColumn('patients', 'doctor_started_at')) {
                    $table->dropColumn('doctor_started_at');
                }
                if (Schema::hasColumn('patients', 'completed_at')) {
                    $table->dropColumn('completed_at');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'is_active')) {
                    $table->dropColumn('is_active');
                }
            });
        }
    }
};
