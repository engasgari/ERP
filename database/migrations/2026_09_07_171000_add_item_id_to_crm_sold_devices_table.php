<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_sold_devices', function (Blueprint $table) {
            $table->foreignId('item_id')
                ->nullable()
                ->after('party_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_sold_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
        });
    }
};
