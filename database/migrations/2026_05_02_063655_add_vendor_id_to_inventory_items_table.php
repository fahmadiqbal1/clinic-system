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
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('is_active')
                  ->constrained('vendors')->nullOnDelete();
            $table->string('pack_size')->nullable()->after('unit');
        });

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('department')
                  ->constrained('vendors')->nullOnDelete();
            $table->string('po_dispatch_status')->nullable()->after('status');
            $table->timestamp('po_sent_at')->nullable()->after('po_dispatch_status');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'po_dispatch_status', 'po_sent_at']);
        });
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'pack_size']);
        });
    }
};
