<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('category', [
                'pharmaceutical',
                'external_lab',
                'lab_supplies',
                'equipment_maintenance',
                'general',
            ])->default('general')->after('is_approved');

            $table->string('mou_document_path')->nullable()->after('category');
            $table->decimal('mou_commission_pct', 5, 2)->nullable()->after('mou_document_path');
            $table->date('mou_valid_until')->nullable()->after('mou_commission_pct');
            $table->unsignedBigInteger('vendor_user_id')->nullable()->after('mou_valid_until');

            $table->foreign('vendor_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        Schema::table('external_labs', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable()->after('is_active');

            $table->foreign('vendor_id')
                ->references('id')->on('vendors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('external_labs', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['vendor_user_id']);
            $table->dropColumn([
                'category',
                'mou_document_path',
                'mou_commission_pct',
                'mou_valid_until',
                'vendor_user_id',
            ]);
        });
    }
};
