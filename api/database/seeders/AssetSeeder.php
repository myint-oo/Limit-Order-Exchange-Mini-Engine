<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyer = DB::table('users')->where('email', 'buyer@example.com')->first();
        $seller = DB::table('users')->where('email', 'seller@example.com')->first();

        // Buyer has some BTC and ETH
        DB::table('assets')->insert([
            [
                'user_id' => $buyer->id,
                'symbol' => 'BTC',
                'amount' => 2.50000000,
                'locked_amount' => 0.00000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $buyer->id,
                'symbol' => 'ETH',
                'amount' => 10.00000000,
                'locked_amount' => 0.00000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Seller has more BTC and ETH (to sell)
        DB::table('assets')->insert([
            [
                'user_id' => $seller->id,
                'symbol' => 'BTC',
                'amount' => 5.00000000,
                'locked_amount' => 0.00000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $seller->id,
                'symbol' => 'ETH',
                'amount' => 20.00000000,
                'locked_amount' => 0.00000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
