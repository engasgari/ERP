<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_intelligence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('review_status', 24)->default('pending_review');
            $table->string('identity_status', 24)->nullable();
            $table->string('confidence', 16)->nullable();
            $table->json('result')->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sources_checked_at')->nullable();
            $table->timestamps();

            $table->index(['party_id', 'created_at']);
            $table->index(['party_id', 'status']);
        });

        Schema::create('crm_customer_intelligence_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_intelligence_id');
            $table->foreign('customer_intelligence_id', 'crm_ci_sources_ci_fk')
                ->references('id')
                ->on('crm_customer_intelligence')
                ->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('domain', 255);
            $table->string('title', 500)->nullable();
            $table->string('source_type', 32)->default('web');
            $table->string('fetch_status', 24);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->json('claims')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_intelligence_sources');
        Schema::dropIfExists('crm_customer_intelligence');
    }
};
