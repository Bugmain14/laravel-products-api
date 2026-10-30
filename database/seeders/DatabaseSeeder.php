<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CurrencySeeder::class);

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        $usd = Currency::where('name', 'USD')->first();

        Product::factory()
            ->count(10)
            ->create(['currency_id' => $usd->id]);
    }
}
