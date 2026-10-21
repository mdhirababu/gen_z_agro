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
        Schema::create('weight_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('cow_id')->constrained('cows')->restrictOnDelete();
            $table->decimal('weight_kg', 7, 2);
            $table->date('measured_at');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cow_id', 'measured_at']);
            $table->index('farm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_records');
    }
};
