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
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();

            $table->string('phone', 20);
            $table->string('kind', 20);                       // task_digest, test
            $table->date('for_date')->nullable();             // সারসংক্ষেপ কোন দিনের (দিনে একবারের নিয়মের জন্য)
            $table->text('message');
            $table->unsignedTinyInteger('segments')->default(1);

            $table->string('status', 10)->default('queued');  // queued, sent, failed, skipped
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('provider_response')->nullable();
            $table->string('error')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'kind', 'for_date']);
            $table->index(['farm_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
