<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->date('work_date'); // تاریخ کارکرد
            $table->time('start_time'); // ساعت شروع
            $table->time('end_time'); // ساعت پایان
            $table->decimal('hours', 5, 2); // ساعت کارکرد
            $table->text('description')->nullable(); // شرح کار
            $table->decimal('hourly_rate', 10, 2); // نرخ ساعتی
            $table->decimal('total_amount', 10, 2); // مبلغ کل
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
