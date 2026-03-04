<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zakat_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_revenue', 12, 2);
            $table->decimal('total_cogs', 12, 2);
            $table->decimal('total_commissions', 12, 2);
            $table->decimal('total_expenses', 12, 2);
            $table->decimal('owner_net_profit', 12, 2);
            $table->decimal('zakat_amount', 12, 2);
            $table->decimal('zakat_percentage', 5, 2)->default(10.00);
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_transactions');
    }
};
