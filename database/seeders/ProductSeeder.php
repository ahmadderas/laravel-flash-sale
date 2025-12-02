<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'Flash Sale Product - Limited Edition',
            'price' => 49.99,
            'total_stock' => 100, // Limited stock for flash sale
        ]);
        
        $this->command->info('✅ Product seeded successfully!');
        $this->command->info('   Product ID: 1');
        $this->command->info('   Stock: 100 units');
        $this->command->info('   Price: $49.99');
    }
}