<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_user', function (Blueprint $table) {
            // কোন সদস্য এই খামারের শুরুর গাইড লুকিয়ে রেখেছেন (প্রত্যেকের নিজের জন্য আলাদা)
            $table->timestamp('onboarding_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('farm_user', function (Blueprint $table) {
            $table->dropColumn('onboarding_dismissed_at');
        });
    }
};
