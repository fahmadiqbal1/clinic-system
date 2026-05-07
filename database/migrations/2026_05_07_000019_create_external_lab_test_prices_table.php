<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_lab_test_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_lab_id');
            $table->string('test_name');
            $table->string('test_code')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('PKR');
            $table->decimal('commission_pct', 5, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedBigInteger('source_price_list_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('external_lab_id')->references('id')->on('external_labs')->cascadeOnDelete();
            $table->foreign('source_price_list_id')->references('id')->on('vendor_price_lists')->nullOnDelete();

            $table->unique(['external_lab_id', 'test_name', 'effective_from'], 'eltp_lab_test_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_lab_test_prices');
    }
};
