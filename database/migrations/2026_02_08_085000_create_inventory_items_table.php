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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->enum('department', ['pharmacy', 'laboratory', 'radiology']);
            $table->string('name');
            $table->string('chemical_formula')->nullable();
            $table->string('sku')->unique();
            $table->string('unit'); // tablet, vial, ml, roll, kit, etc.
            $table->integer('quantity_in_stock')->default(0);
            $table->integer('minimum_stock_level')->default(0);
            $table->decimal('purchase_price', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->boolean('requires_prescription')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('department');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
