<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('detail_code')->unique();
            $table->enum('kind', ['person', 'company'])->default('person');
            $table->string('name');
            $table->string('economic_code')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('party_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('party_party_type', function (Blueprint $table) {
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['party_id', 'party_type_id']);
        });

        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['product', 'service'])->default('product');
            $table->string('name');
            $table->foreignId('measurement_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->nullable();
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('jalali_year')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chart_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('chart_accounts')->cascadeOnDelete();
            $table->enum('level', ['group', 'ledger', 'subsidiary', 'detail']);
            $table->string('code')->unique();
            $table->string('title');
            $table->enum('nature', ['debit', 'credit', 'neutral'])->default('neutral');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('accounting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->date('document_date');
            $table->enum('type', ['manual', 'sale_invoice', 'purchase_invoice', 'payment', 'receipt', 'inventory', 'closing'])->default('manual');
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->nullableMorphs('source');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['sale', 'purchase']);
            $table->enum('document_type', ['proforma', 'invoice'])->default('invoice');
            $table->string('number')->unique();
            $table->date('invoice_date');
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->foreignId('converted_from_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->foreignId('accounting_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->enum('type', ['receipt', 'issue', 'transfer']);
            $table->date('document_date');
            $table->time('document_time')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->enum('entry_mode', ['manual', 'automatic'])->default('manual');
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('accounting_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('unit_price_usd', 15, 2)->nullable();
            $table->decimal('line_total', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('numbering_settings', function (Blueprint $table) {
            $table->id();
            $table->string('document_key')->unique();
            $table->string('prefix')->nullable();
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('economic_code')->nullable();
            $table->string('national_id')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('default_vat_rate', 6, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('numbering_settings');
        Schema::dropIfExists('inventory_document_lines');
        Schema::dropIfExists('inventory_documents');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('accounting_document_lines');
        Schema::dropIfExists('accounting_documents');
        Schema::dropIfExists('chart_accounts');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('items');
        Schema::dropIfExists('measurement_units');
        Schema::dropIfExists('party_party_type');
        Schema::dropIfExists('party_types');
        Schema::dropIfExists('parties');
    }
};
