<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->integer('month'); // 1 تا 12
            $table->decimal('total_hours', 8, 2)->default(0); // مجموع ساعت کار
            $table->decimal('hourly_rate', 10, 2); // نرخ ساعتی
            $table->decimal('base_salary', 12, 2)->default(0); // حقوق پایه
            $table->decimal('overtime_hours', 8, 2)->default(0); // ساعت اضافه کاری
            $table->decimal('overtime_rate', 10, 2)->default(0); // نرخ اضافه کاری
            $table->decimal('overtime_salary', 12, 2)->default(0); // حقوق اضافه کاری
            $table->decimal('bonus', 12, 2)->default(0); // پاداش
            $table->decimal('deduction', 12, 2)->default(0); // کسورات
            $table->decimal('net_salary', 12, 2)->default(0); // حقوق خالص
            $table->decimal('advance_payment', 12, 2)->default(0); // تنخواه
            $table->decimal('final_salary', 12, 2)->default(0); // حقوق نهایی
            $table->enum('status', ['draft', 'calculated', 'paid', 'partial'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
