<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patient_checkins')) {
            return;
        }
        Schema::create('patient_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('checked_in_by')->nullable()->index();
            $table->timestamp('checked_in_at');
            $table->enum('checked_in_via', ['kiosk', 'staff'])->default('kiosk');
            $table->timestamps();
            $table->index(['patient_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_checkins');
    }
};
