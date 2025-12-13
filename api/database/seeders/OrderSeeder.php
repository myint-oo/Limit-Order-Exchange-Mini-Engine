<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyer = DB::table('users')->where('email', 'buyer@example.com')->first();
        $seller = DB::table('users')->where('email', 'seller@example.com')->first();

        // Buyer's buy orders
        DB::table('orders')->insert([
            [
                'user_id' => $buyer->id,
                'symbol' => 'BTC',
                'side' => 'buy',
                'price' => 42000.00000000,
                'amount' => 0.50000000,
                'status' => 'open',
                'locked_funds' => 21000.00000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $buyer->id,
                'symbol' => 'ETH',
                'side' => 'buy',
                'price' => 2200.00000000,
                'amount' => 2.00000000,
                'status' => 'open',
                'locked_funds' => 4400.00000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Seller's sell orders
        DB::table('orders')->insert([
            [
                'user_id' => $seller->id,
                'symbol' => 'BTC',
                'side' => 'sell',
                'price' => 43000.00000000,
                'amount' => 1.00000000,
                'status' => 'open',
                'locked_funds' => 1.00000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $seller->id,
                'symbol' => 'ETH',
                'side' => 'sell',
                'price' => 2300.00000000,
                'amount' => 5.00000000,
                'status' => 'open',
                'locked_funds' => 5.00000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
