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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('cow_id')->nullable()->constrained('cows')->restrictOnDelete();

            $table->string('category', 20);              // config('farm.expense_categories')-এর key
            $table->date('expense_date');
            $table->string('description')->nullable();
            $table->string('vendor_name', 100)->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();

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

            $table->index(['farm_id', 'expense_date']);
            $table->index(['farm_id', 'cow_id', 'expense_date']);
            $table->index(['farm_id', 'status', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
