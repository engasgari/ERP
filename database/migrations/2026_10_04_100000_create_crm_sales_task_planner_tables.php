<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sales_task_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('prompt_version', 32)->nullable();
            $table->string('model', 128)->nullable();
            $table->unsignedSmallInteger('candidates_count')->default(0);
            $table->unsignedSmallInteger('suggestions_count')->default(0);
            $table->string('failure_reason', 500)->nullable();
            $table->text('planner_note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('crm_sales_task_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->foreign('plan_id', 'crm_stp_suggestions_plan_fk')
                ->references('id')
                ->on('crm_sales_task_plans')
                ->cascadeOnDelete();
            $table->string('candidate_key', 64);
            $table->string('candidate_kind', 48);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->unsignedSmallInteger('due_offset_business_hours')->nullable();
            $table->unsignedSmallInteger('rank')->default(0);
            $table->text('reason')->nullable();
            $table->json('supporting_facts')->nullable();
            $table->string('review_status', 24)->default('pending_review');
            $table->foreignId('applied_task_id')->nullable()->constrained('crm_tasks')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'review_status']);
            $table->index(['assigned_user_id', 'review_status']);
            $table->unique(['plan_id', 'candidate_key'], 'crm_stp_plan_candidate_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sales_task_suggestions');
        Schema::dropIfExists('crm_sales_task_plans');
    }
};
