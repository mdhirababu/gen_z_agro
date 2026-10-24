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
        Schema::create('feed_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('feed_type_id')->constrained('feed_types')->restrictOnDelete();

            $table->date('fed_on');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 10);                              // রেকর্ডের সময়ের একক (কপি)

            $table->string('target', 10);                            // all, category, cow
            $table->string('category', 20)->nullable();
            $table->foreignId('cow_id')->nullable()->constrained('cows')->restrictOnDelete();
            $table->unsignedInteger('head_count')->default(1);       // রেকর্ডের সময়ের সক্রিয় গরুর সংখ্যা

            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['farm_id', 'fed_on']);
            $table->index(['farm_id', 'feed_type_id', 'fed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_records');
    }
};
