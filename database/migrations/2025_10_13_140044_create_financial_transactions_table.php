<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['income', 'expense']); // درآمد یا هزینه
            $table->string('category'); // دسته‌بندی
            $table->decimal('amount', 15, 2); // مبلغ
            $table->date('transaction_date'); // تاریخ تراکنش
            $table->text('description')->nullable(); // شرح تراکنش
            $table->string('reference_number')->nullable(); // شماره مرجع (شماره فاکتور و...)
            $table->string('attachment')->nullable(); // فایل پیوست
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
