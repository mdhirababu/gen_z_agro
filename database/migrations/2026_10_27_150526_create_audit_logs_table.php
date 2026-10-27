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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // ইচ্ছে করে foreign key নেই: খামার বা ব্যবহারকারী মুছলেও লগ থেকে যাবে
            $table->unsignedBigInteger('farm_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_name', 100)->nullable();

            $table->string('event', 30);                    // created, updated, deleted, restored, voided, exported
            $table->string('auditable_type', 60);           // Sale, Expense, Cow, FarmMember, Report ...
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('subject', 150)->nullable();     // "গরু CW101", "বিক্রি #12"

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('meta')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('source', 10)->default('web');   // web বা console

            $table->timestamp('created_at')->useCurrent();

            $table->index(['farm_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
