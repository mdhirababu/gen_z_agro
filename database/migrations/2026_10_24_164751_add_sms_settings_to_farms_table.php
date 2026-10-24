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
        Schema::table('farms', function (Blueprint $table) {
            $table->boolean('sms_enabled')->default(false)->after('currency');
            $table->string('sms_phone', 30)->nullable()->after('sms_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropColumn(['sms_enabled', 'sms_phone']);
        });
    }
};
