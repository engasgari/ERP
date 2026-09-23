<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            if (! Schema::hasColumn('parties', 'registration_number')) {
                $table->string('registration_number')->nullable()->after('economic_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            if (Schema::hasColumn('parties', 'registration_number')) {
                $table->dropColumn('registration_number');
            }
        });
    }
};
