<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 64)->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order');
            $table->decimal('probability', 5, 2)->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pipeline_id', 'sort_order']);
            $table->index(['pipeline_id', 'sort_order']);
        });

        Schema::create('crm_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->unique()->constrained('parties')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('active');
            $table->string('industry')->nullable();
            $table->string('city')->nullable();
            $table->string('website')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('crm_lead_sources')->nullOnDelete();
            $table->unsignedTinyInteger('customer_score')->nullable();
            $table->string('customer_segment')->nullable();
            $table->text('crm_notes')->nullable();
            $table->date('first_contact_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('assigned_user_id');
            $table->index('status');
            $table->index('last_activity_at');
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_decision_maker')->default(false);
            $table->boolean('is_influencer')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['party_id', 'is_primary']);
            $table->index('assigned_user_id');
        });

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('title');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('crm_lead_sources')->nullOnDelete();
            $table->string('status', 32)->default('new');
            $table->string('rating', 16)->nullable();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('converted_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('converted_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->unsignedBigInteger('converted_opportunity_id')->nullable();
            $table->dateTime('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lost_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('assigned_user_id');
            $table->index('mobile');
            $table->index('email');
        });

        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('title');
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('crm_pipeline_stages')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('probability', 5, 2)->default(0);
            $table->decimal('expected_amount', 18, 2)->default(0);
            $table->date('expected_close_date')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('crm_lead_sources')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->text('description')->nullable();
            $table->string('lost_reason')->nullable();
            $table->dateTime('won_at')->nullable();
            $table->dateTime('lost_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['party_id', 'status']);
            $table->index(['stage_id', 'status']);
            $table->index('assigned_user_id');
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->foreign('converted_opportunity_id')->references('id')->on('crm_opportunities')->nullOnDelete();
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('activitable_type');
            $table->unsignedBigInteger('activitable_id');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status', 16)->default('planned');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['activitable_type', 'activitable_id']);
            $table->index(['party_id', 'created_at']);
            $table->index(['assigned_user_id', 'status', 'due_at']);
        });

        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('reminder_at')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 16)->default('pending');
            $table->string('taskable_type')->nullable();
            $table->unsignedBigInteger('taskable_id')->nullable();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['taskable_type', 'taskable_id']);
            $table->index(['party_id', 'created_at']);
            $table->index(['assigned_user_id', 'status', 'due_at']);
        });

        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->string('notable_type');
            $table->unsignedBigInteger('notable_id');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['notable_type', 'notable_id']);
            $table->index(['party_id', 'created_at']);
        });

        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('color', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->primary(['tag_id', 'taggable_type', 'taggable_id']);
            $table->index(['taggable_type', 'taggable_id']);
        });

        Schema::create('crm_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('party_id');
        });

        Schema::create('crm_audits', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('event', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['party_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_audits');
        Schema::dropIfExists('crm_attachments');
        Schema::dropIfExists('crm_taggables');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_activities');
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropForeign(['converted_opportunity_id']);
        });
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_customer_profiles');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
        Schema::dropIfExists('crm_lead_sources');
    }
};
