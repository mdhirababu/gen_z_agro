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
        Schema::create('breeding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('cow_id')->constrained('cows')->restrictOnDelete();

            $table->date('service_date');
            $table->string('method', 10);                    // ai, natural
            $table->string('sire_name', 100)->nullable();    // বীজ বা ষাঁড়ের নাম, জাত
            $table->string('technician', 100)->nullable();   // যিনি করেছেন

            $table->string('status', 12)->default('served'); // served, pregnant, open, aborted, calved
            $table->date('next_check_date')->nullable();     // গর্ভ পরীক্ষার তারিখ (শুধু served অবস্থায়)
            $table->date('checked_on')->nullable();
            $table->date('expected_calving_date')->nullable();
            $table->date('outcome_date')->nullable();        // প্রসব, গর্ভপাত বা "গর্ভ হয়নি"-র তারিখ

            $table->text('notes')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['farm_id', 'status']);
            $table->index(['cow_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeding_events');
    }
};
