<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_price_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('external_lab_id')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->enum('file_type', ['pdf', 'image', 'csv', 'manual']);
            $table->enum('status', [
                'pending',
                'processing',
                'extracted',
                'applied',
                'flagged',
                'failed',
            ])->default('pending');
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->json('extraction_summary')->nullable();
            $table->json('flag_reasons')->nullable();
            $table->integer('item_count')->nullable();
            $table->integer('flagged_count')->nullable();
            $table->integer('applied_count')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('external_lab_id')->references('id')->on('external_labs')->nullOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('applied_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('vendor_price_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_price_list_id');
            $table->string('item_name');
            $table->string('sku_detected')->nullable();
            $table->string('unit_detected')->nullable();
            $table->decimal('detected_price', 10, 2)->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->unsignedBigInteger('external_lab_id')->nullable();
            $table->string('test_name_normalized')->nullable();
            $table->decimal('confidence', 3, 2)->default(0);
            $table->boolean('needs_review')->default(false);
            $table->boolean('applied')->default(false);
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('vendor_price_list_id')->references('id')->on('vendor_price_lists')->cascadeOnDelete();
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->nullOnDelete();
            $table->foreign('external_lab_id')->references('id')->on('external_labs')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_price_items');
        Schema::dropIfExists('vendor_price_lists');
    }
};
