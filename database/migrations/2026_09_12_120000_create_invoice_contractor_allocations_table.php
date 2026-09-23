<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_contractor_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('contractor_party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('purchase_invoice_id');
            $table->index('contractor_party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_contractor_allocations');
    }
};
