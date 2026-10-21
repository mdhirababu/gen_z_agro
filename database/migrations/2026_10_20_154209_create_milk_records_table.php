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
        Schema::create('milk_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('cow_id')->constrained('cows')->restrictOnDelete();
            $table->date('milk_date');

            $table->decimal('session_1', 6, 2)->default(0);
            $table->decimal('session_2', 6, 2)->default(0);
            $table->decimal('session_3', 6, 2)->default(0);
            $table->decimal('total_liters', 7, 2)->default(0);

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->unique(['cow_id', 'milk_date']);
            $table->index(['farm_id', 'milk_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_records');
    }
};
