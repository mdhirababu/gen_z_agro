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
        Schema::create('health_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('cow_id')->constrained('cows')->restrictOnDelete();

            $table->string('kind', 20);                         // treatment, vaccination, deworming, checkup
            $table->date('event_date');
            $table->string('vaccine_code', 20)->nullable();     // vaccination হলে config('farm.vaccines')-এর key
            $table->string('title');                            // রোগের নাম, টিকার নাম ইত্যাদি
            $table->text('details')->nullable();
            $table->string('medicine')->nullable();
            $table->string('vet_name', 100)->nullable();

            $table->string('outcome', 12)->nullable();          // শুধু treatment: ongoing, recovered
            $table->date('milk_withdrawal_until')->nullable();  // এই তারিখ পর্যন্ত দুধ বিক্রি বন্ধ

            $table->date('next_due_date')->nullable();          // পরের টিকা বা পরীক্ষার তারিখ
            $table->string('follow_up_status', 10)->nullable(); // open, done

            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['farm_id', 'event_date']);
            $table->index(['cow_id', 'event_date']);
            $table->index(['farm_id', 'follow_up_status', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_events');
    }
};
