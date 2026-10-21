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
        Schema::create('cows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('mother_id')->nullable()->constrained('cows')->nullOnDelete();

            $table->string('tag_number', 50);
            $table->string('name', 100)->nullable();
            $table->string('category', 20);              // milking, dry, heifer, calf, bull, fattening
            $table->string('breed', 40)->nullable();
            $table->string('sex', 10);                   // female, male

            $table->date('birth_date')->nullable();
            $table->boolean('birth_date_estimated')->default(false);
            $table->string('sire_name', 100)->nullable();

            $table->string('acquisition_type', 20);      // born, purchased, existing
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('opening_value', 14, 2)->default(0);

            $table->string('status', 20)->default('active'); // active, sold, dead, transferred
            $table->date('exit_date')->nullable();

            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual'); // manual (পরে: import, ai_draft)

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['farm_id', 'tag_number']);
            $table->index(['farm_id', 'status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cows');
    }
};
