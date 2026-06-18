<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            if (!Schema::hasColumn('fiscal_years', 'currency')) {
                $table->string('currency', 10)->default('IRR')->after('end_date');
            }
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('period_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'period_number']);
        });

        Schema::table('accounting_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('accounting_documents', 'fiscal_period_id')) {
                $table->foreignId('fiscal_period_id')->nullable()->after('fiscal_year_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_documents', 'currency')) {
                $table->string('currency', 10)->default('IRR')->after('status');
            }
            if (!Schema::hasColumn('accounting_documents', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('accounting_documents', 'posted_by')) {
                $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_documents', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('posted_by');
            }
            if (!Schema::hasColumn('accounting_documents', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_documents', 'notes')) {
                $table->text('notes')->nullable()->after('description');
            }
            if (!Schema::hasColumn('accounting_documents', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('accounting_document_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('accounting_document_lines', 'detail_account_id')) {
                $table->foreignId('detail_account_id')->nullable()->after('chart_account_id')->constrained('chart_accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_document_lines', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('party_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('accounting_document_lines', 'cost_center')) {
                $table->string('cost_center')->nullable()->after('project_id');
            }
            if (!Schema::hasColumn('accounting_document_lines', 'currency')) {
                $table->string('currency', 10)->default('IRR')->after('credit');
            }
            if (!Schema::hasColumn('accounting_document_lines', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
            }
            if (!Schema::hasColumn('accounting_document_lines', 'bank_account_id')) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('exchange_rate');
            }
            if (!Schema::hasColumn('accounting_document_lines', 'cashbox_id')) {
                $table->unsignedBigInteger('cashbox_id')->nullable()->after('bank_account_id');
            }
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->string('account_number')->nullable();
            $table->string('iban')->nullable();
            $table->string('card_number')->nullable();
            $table->string('currency', 10)->default('IRR');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->foreignId('chart_account_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cashboxes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('currency', 10)->default('IRR');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->foreignId('chart_account_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->enum('type', ['deposit', 'withdrawal', 'transfer', 'cash_receipt', 'cash_payment', 'bank_receipt', 'bank_payment']);
            $table->date('transaction_date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('IRR');
            $table->nullableMorphs('from_treasury');
            $table->nullableMorphs('to_treasury');
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->foreignId('income_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->nullableMorphs('source');
            $table->foreignId('accounting_document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('receipt_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->enum('type', ['customer', 'miscellaneous'])->default('customer');
            $table->date('voucher_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('treasury');
            $table->foreignId('income_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->foreignId('accounting_document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->enum('type', ['supplier', 'expense'])->default('supplier');
            $table->date('voucher_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('treasury');
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->foreignId('accounting_document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_audits', function (Blueprint $table) {
            $table->id();
            $table->morphs('auditable');
            $table->string('event');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audits');
        Schema::dropIfExists('accounting_attachments');
        Schema::dropIfExists('payment_vouchers');
        Schema::dropIfExists('receipt_vouchers');
        Schema::dropIfExists('treasury_transactions');
        Schema::dropIfExists('cashboxes');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('fiscal_periods');
    }
};
