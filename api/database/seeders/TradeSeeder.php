<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyer = DB::table('users')->where('email', 'buyer@example.com')->first();
        $seller = DB::table('users')->where('email', 'seller@example.com')->first();

        // Get first orders for reference (assuming they exist)
        $buyOrder = DB::table('orders')->where('user_id', $buyer->id)->first();
        $sellOrder = DB::table('orders')->where('user_id', $seller->id)->first();

        if ($buyOrder && $sellOrder) {
            // Sample completed trade
            DB::table('trades')->insert([
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'buy_order_id' => $buyOrder->id,
                'sell_order_id' => $sellOrder->id,
                'symbol' => 'BTC',
                'price' => 42500.00000000,
                'amount' => 0.10000000,
                'total' => 4250.00000000,
                'fee' => 4.25000000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
