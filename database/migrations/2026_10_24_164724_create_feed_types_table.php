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
        Schema::create('feed_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('unit', 10);                              // config('farm.feed_units')-এর key
            $table->decimal('price_per_unit', 10, 2)->nullable();    // আনুমানিক হিসাবের জন্য
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['farm_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_types');
    }
};
