<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Two demo tenants, one per supported locale, so a fresh clone can be explored
 * in both text directions. Every seeded account uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Tenant::factory()->create([
            'name' => 'Demo Salon',
            'slug' => 'demo',
            'timezone' => 'Asia/Riyadh',
            'locale' => 'en',
        ]);

        User::factory()->for($demo)->owner()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@demo.test',
        ]);

        User::factory()->for($demo)->create([
            'name' => 'Demo Staff',
            'email' => 'staff@demo.test',
        ]);

        $jamal = Tenant::factory()->arabic()->create([
            'name' => 'صالون الجمال',
            'slug' => 'jamal',
            'timezone' => 'Asia/Riyadh',
        ]);

        User::factory()->for($jamal)->owner()->create([
            'name' => 'نورة',
            'email' => 'owner@jamal.test',
        ]);
    }
}
