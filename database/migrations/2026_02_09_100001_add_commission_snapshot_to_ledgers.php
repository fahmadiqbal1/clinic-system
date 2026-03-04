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
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            // Commission snapshot fields for audit trail & financial integrity
            $table->decimal('net_cost', 10, 2)->nullable()->after('amount')->comment('WAC cost at time of invoice');
            $table->decimal('gross_profit', 10, 2)->nullable()->after('net_cost')->comment('total_amount - net_cost');
            $table->decimal('commission_amount', 10, 2)->nullable()->after('gross_profit')->comment('amount allocated to this role');
            $table->boolean('is_prescribed', false)->after('commission_amount')->comment('true if doctor-prescribed item (pharmacy only)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_ledgers', function (Blueprint $table) {
            $table->dropColumn(['net_cost', 'gross_profit', 'commission_amount', 'is_prescribed']);
        });
    }
};
