<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('registration_type')->default('regular')->after('status')
                ->comment('regular = registered by receptionist; referral = quick-registered by independent doctor');
            $table->unsignedBigInteger('referred_by_user_id')->nullable()->after('registration_type')
                ->comment('Independent doctor who referred this patient');

            $table->foreign('referred_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn(['registration_type', 'referred_by_user_id']);
        });
    }
};
