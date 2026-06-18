<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'project_number')) {
                $table->string('project_number')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('projects', 'party_id')) {
                $table->foreignId('party_id')->nullable()->after('name')->constrained('parties')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'project_manager_id')) {
                $table->foreignId('project_manager_id')->nullable()->after('party_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'budget')) {
                $table->decimal('budget', 18, 2)->default(0)->after('end_date');
            }
            if (!Schema::hasColumn('projects', 'cost_center_code')) {
                $table->string('cost_center_code')->nullable()->after('budget');
            }
            if (!Schema::hasColumn('projects', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('status');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('party_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('inventory_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_documents', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('target_warehouse_id')->constrained()->nullOnDelete();
            }
        });

        Schema::create('bom_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('version_number')->default('1');
            $table->string('revision')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->date('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['item_id', 'version_number']);
        });

        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->foreignId('measurement_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('waste_percentage', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('bom_version_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->enum('status', ['draft', 'approved', 'in_progress', 'testing', 'completed', 'closed'])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_material_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('planned_quantity', 15, 3)->default(0);
            $table->decimal('actual_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('variance_quantity', 15, 3)->default(0);
            $table->enum('status', ['planned', 'reserved', 'consumed'])->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('project_overhead_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['labor_hours', 'material_cost', 'project_value', 'fixed_percentage', 'manual'])->default('manual');
            $table->decimal('base_amount', 18, 2)->default(0);
            $table->decimal('rate', 8, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('allocated_date')->nullable();
            $table->foreignId('accounting_document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_cost_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('material_cost', 18, 2)->default(0);
            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('service_cost', 18, 2)->default(0);
            $table->decimal('overhead_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->decimal('revenue', 18, 2)->default(0);
            $table->decimal('gross_profit', 18, 2)->default(0);
            $table->decimal('profit_margin', 8, 2)->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_cost_snapshots');
        Schema::dropIfExists('project_overhead_allocations');
        Schema::dropIfExists('production_material_consumptions');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('bom_lines');
        Schema::dropIfExists('bom_versions');
    }
};
