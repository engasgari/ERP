<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Hash;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@aale.ir',
            'password' => Hash::make('password')
        ]);

        $this->call(AccessControlSeeder::class);
        $this->call(BusinessCoreSeeder::class);
        $this->call(WorkCalendarSeeder::class);
        $this->call(AccountingTreasurySeeder::class);
        $this->call(StandardPayrollSeeder::class);
        $this->call(SmallBusinessHrSeeder::class);
        $this->call(StandardPayrollSeeder::class);
    }
}
