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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();

            $table->string('payable_type', 20);          // expense, sale
            $table->unsignedBigInteger('payable_id');

            $table->decimal('amount', 14, 2);
            $table->date('paid_at');
            $table->string('method', 20)->default('cash');
            $table->string('note')->nullable();

            $table->string('status', 10)->default('active'); // active, void
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['farm_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
