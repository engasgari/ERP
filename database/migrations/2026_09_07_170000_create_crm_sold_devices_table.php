<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sold_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('serial_number', 128);
            $table->string('device_name')->nullable();
            $table->date('sold_at');
            $table->unsignedTinyInteger('warranty_years')->default(1);
            $table->date('warranty_ends_at');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('serial_number');
            $table->index(['party_id', 'sold_at']);
            $table->index('warranty_ends_at');
            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sold_devices');
    }
};
