<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items')) {
            DB::statement('ALTER TABLE items MODIFY sale_price DECIMAL(15,2) NULL');
            DB::statement('ALTER TABLE items MODIFY purchase_price DECIMAL(15,2) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items')) {
            DB::statement('ALTER TABLE items MODIFY sale_price DECIMAL(15,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE items MODIFY purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0');
        }
    }
};
