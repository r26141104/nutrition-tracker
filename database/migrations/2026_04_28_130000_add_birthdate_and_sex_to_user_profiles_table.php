<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // 設為 nullable，現有的 profile 暫時是 NULL，之後使用者可回 /profile 補填
            $table->date('birthdate')->nullable()->after('user_id');
            $table->string('sex', 10)->nullable()->after('birthdate'); // male | female
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['birthdate', 'sex']);
        });
    }
};
