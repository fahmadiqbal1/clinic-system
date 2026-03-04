<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old per-user-type override table (superseded)
        Schema::dropIfExists('user_compensations');

        // Drop old department-based commission configs (replacing with user-based)
        Schema::dropIfExists('commission_configs');

        // New commission_configs: per-user, per-service-type
        // Each row = "For [service_type], user [user_id] with role [role] gets [percentage]%"
        // Owner is NEVER stored as a row — Owner always absorbs the remainder to guarantee 100%
        Schema::create('commission_configs', function (Blueprint $table) {
            $table->id();
            $table->string('service_type'); // consultation, pharmacy, radiology, lab
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role'); // doctor, pharmacist, charity, technician, referrer
            $table->decimal('percentage', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Each user can only have one config per service_type
            $table->unique(['service_type', 'user_id']);
            $table->index(['service_type', 'is_active']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_configs');

        // Restore old tables (approximate)
        Schema::create('commission_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('department', ['lab', 'radiology', 'pharmacy', 'consultation'])->unique();
            $table->decimal('owner_percentage', 5, 2);
            $table->decimal('staff_percentage', 5, 2)->nullable();
            $table->decimal('doctor_percentage', 5, 2)->nullable();
            $table->decimal('pharmacist_percentage', 5, 2)->nullable();
            $table->decimal('doctor_pharmacy_commission', 5, 2)->nullable();
            $table->boolean('allow_referrer')->default(false);
            $table->timestamps();
        });

        Schema::create('user_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('department');
            $table->enum('compensation_type', ['commission', 'fixed']);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'department']);
        });
    }
};
