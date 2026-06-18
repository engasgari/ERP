<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('employee_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['debit', 'credit'])->comment('debit: بدهکار, credit: بستانکار');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('transaction_date');
            $table->text('description');
            $table->enum('reference_type', ['salary', 'payment', 'advance', 'bonus', 'deduction', 'other'])->default('other');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('transaction_date');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_transactions');
    }
}
