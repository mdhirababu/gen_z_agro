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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();

            $table->string('sale_type', 20);                 // milk, cow, manure, other_income
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('cow_id')->nullable()->constrained('cows')->restrictOnDelete();

            $table->date('sold_at');
            $table->string('description')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();

            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('payment_status', 10)->default('unpaid'); // unpaid, partial, paid

            $table->string('status', 10)->default('active');         // active, void
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->index(['farm_id', 'sold_at']);
            $table->index(['farm_id', 'sale_type', 'status']);
            $table->index(['customer_id', 'status', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
