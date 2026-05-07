<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('specialty')->nullable()->after('commission_radiology');
            $table->timestamp('credentials_submitted_at')->nullable()->after('specialty');
            $table->timestamp('credentials_verified_at')->nullable()->after('credentials_submitted_at');
            $table->unsignedBigInteger('credentials_verified_by')->nullable()->after('credentials_verified_at');

            $table->foreign('credentials_verified_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['credentials_verified_by']);
            $table->dropColumn([
                'specialty',
                'credentials_submitted_at',
                'credentials_verified_at',
                'credentials_verified_by',
            ]);
        });
    }
};
