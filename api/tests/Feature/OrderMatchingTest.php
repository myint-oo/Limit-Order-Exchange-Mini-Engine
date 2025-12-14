<?php

use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Services\MatchingEngine;
use App\Services\OrderService;
use App\Services\WalletService;

beforeEach(function () {
    // Create two users for testing trades
    $this->buyer = User::factory()->create(['balance' => '100000.00000000']);
    $this->seller = User::factory()->create(['balance' => '50000.00000000']);

    // Give seller some BTC to sell
    Asset::create([
        'user_id' => $this->seller->id,
        'symbol' => 'BTC',
        'amount' => '10.00000000',
        'locked_amount' => '0.00000000',
    ]);

    $this->walletService = app(WalletService::class);
    $this->matchingEngine = app(MatchingEngine::class);
    $this->orderService = app(OrderService::class);
});

describe('Order Placement', function () {
    it('places a buy order and locks USD', function () {
        $result = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($result['success'])->toBeTrue();
        expect($result['order']->status)->toBe('open');
        expect($result['order']->locked_funds)->toBe('50000.00000000');

        // Buyer's balance should be reduced
        $this->buyer->refresh();
        expect($this->buyer->balance)->toBe('50000.00000000');
    });

    it('places a sell order and locks asset', function () {
        $result = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($result['success'])->toBeTrue();
        expect($result['order']->status)->toBe('open');
        expect($result['order']->locked_funds)->toBe('1.00000000');

        // Seller's asset should be locked
        $asset = Asset::where('user_id', $this->seller->id)->where('symbol', 'BTC')->first();
        expect($asset->locked_amount)->toBe('1.00000000');
    });

    it('rejects buy order with insufficient balance', function () {
        $result = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '10.00000000', // Would need $500,000 but only has $100,000
        ]);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Insufficient USD balance');
    });

    it('rejects sell order with insufficient asset balance', function () {
        $result = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '20.00000000', // Only has 10 BTC
        ]);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Insufficient BTC balance');
    });
});

describe('Order Matching', function () {
    it('matches buy and sell orders with exact amounts', function () {
        // Seller places a sell order first
        $sellResult = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($sellResult['success'])->toBeTrue();
        expect($sellResult['trade'])->toBeNull(); // No match yet

        // Buyer places a matching buy order
        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($buyResult['success'])->toBeTrue();
        expect($buyResult['trade'])->not->toBeNull(); // Trade executed

        // Verify trade details
        $trade = $buyResult['trade'];
        expect($trade->price)->toBe('50000.00000000');
        expect($trade->amount)->toBe('1.00000000');
        expect($trade->total)->toBe('50000.00000000');
        expect($trade->fee)->toBe('750.00000000'); // 1.5% commission

        // Verify orders are filled
        $sellResult['order']->refresh();
        $buyResult['order']->refresh();
        expect($sellResult['order']->status)->toBe('filled');
        expect($buyResult['order']->status)->toBe('filled');
    });

    it('matches when buy price is higher than sell price', function () {
        // Seller sells at $50,000
        $sellResult = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '0.50000000',
        ]);

        // Buyer willing to pay $55,000
        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '55000.00000000',
            'amount' => '0.50000000',
        ]);

        expect($buyResult['trade'])->not->toBeNull();
        // Trade executes at maker's price ($50,000)
        expect($buyResult['trade']->price)->toBe('50000.00000000');
    });

    it('does not match orders with different amounts', function () {
        // Seller sells 1 BTC
        $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        // Buyer wants 0.5 BTC - no partial fill
        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '0.50000000',
        ]);

        expect($buyResult['trade'])->toBeNull();
        expect($buyResult['order']->status)->toBe('open');
    });

    it('does not match when prices are incompatible', function () {
        // Seller wants $55,000
        $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '55000.00000000',
            'amount' => '1.00000000',
        ]);

        // Buyer only willing to pay $50,000
        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($buyResult['trade'])->toBeNull();
    });

    it('matches sell order against existing buy order', function () {
        // Buyer places buy order first
        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($buyResult['trade'])->toBeNull();

        // Seller places matching sell order
        $sellResult = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($sellResult['trade'])->not->toBeNull();
    });
});

describe('Balance Transfers', function () {
    it('correctly transfers USD and assets after trade', function () {
        $initialBuyerBalance = $this->buyer->balance;
        $initialSellerBalance = $this->seller->balance;
        $initialSellerBtc = Asset::where('user_id', $this->seller->id)->where('symbol', 'BTC')->first()->amount;

        // Execute trade
        $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $this->buyer->refresh();
        $this->seller->refresh();

        // Buyer: 100,000 - 50,000 = 50,000 USD
        expect($this->buyer->balance)->toBe('50000.00000000');

        // Seller: 50,000 + (50,000 - 750 fee) = 50,000 + 49,250 = 99,250 USD
        expect($this->seller->balance)->toBe('99250.00000000');

        // Buyer should now have 1 BTC
        $buyerBtc = Asset::where('user_id', $this->buyer->id)->where('symbol', 'BTC')->first();
        expect($buyerBtc->amount)->toBe('1.00000000');

        // Seller should have 10 - 1 = 9 BTC
        $sellerBtc = Asset::where('user_id', $this->seller->id)->where('symbol', 'BTC')->first();
        expect($sellerBtc->amount)->toBe('9.00000000');
        expect($sellerBtc->locked_amount)->toBe('0.00000000');
    });

    it('refunds excess USD when buyer gets price improvement', function () {
        // Seller sells at $45,000
        $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '45000.00000000',
            'amount' => '1.00000000',
        ]);

        // Buyer willing to pay $50,000 but gets $45,000 price
        $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $this->buyer->refresh();
        // Buyer should have: 100,000 - 45,000 = 55,000 (not 50,000)
        expect($this->buyer->balance)->toBe('55000.00000000');
    });
});

describe('Commission Calculation', function () {
    it('calculates 1.5% commission on trade', function () {
        $usdVolume = '100000.00000000';
        $expectedFee = '1500.00000000';

        $fee = $this->matchingEngine->calculateCommission($usdVolume);
        expect($fee)->toBe($expectedFee);
    });

    it('seller receives USD minus commission', function () {
        $initialSellerBalance = $this->seller->balance;

        // Execute a $50,000 trade
        $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $this->seller->refresh();
        // Commission = 50,000 * 0.015 = 750
        // Seller receives: 50,000 - 750 = 49,250
        // New balance: 50,000 + 49,250 = 99,250
        expect($this->seller->balance)->toBe('99250.00000000');
    });
});

describe('Order Cancellation', function () {
    it('cancels open buy order and refunds USD', function () {
        $result = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $order = $result['order'];
        $this->buyer->refresh();
        expect($this->buyer->balance)->toBe('50000.00000000');

        // Cancel the order
        $cancelResult = $this->orderService->cancelOrder($order, $this->buyer);
        expect($cancelResult['success'])->toBeTrue();

        // USD should be refunded
        $this->buyer->refresh();
        expect($this->buyer->balance)->toBe('100000.00000000');

        // Order should be cancelled
        $order->refresh();
        expect($order->status)->toBe('cancelled');
    });

    it('cancels open sell order and unlocks asset', function () {
        $result = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $order = $result['order'];
        $asset = Asset::where('user_id', $this->seller->id)->where('symbol', 'BTC')->first();
        expect($asset->locked_amount)->toBe('1.00000000');

        // Cancel the order
        $this->orderService->cancelOrder($order, $this->seller);

        // Asset should be unlocked
        $asset->refresh();
        expect($asset->locked_amount)->toBe('0.00000000');
    });

    it('prevents cancelling another user\'s order', function () {
        $result = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $order = $result['order'];

        // Buyer tries to cancel seller's order
        $cancelResult = $this->orderService->cancelOrder($order, $this->buyer);
        expect($cancelResult['success'])->toBeFalse();
        expect($cancelResult['error'])->toContain('Unauthorized');
    });

    it('prevents cancelling a filled order', function () {
        // Create and match orders
        $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $order = $buyResult['order'];
        expect($order->status)->toBe('filled');

        // Try to cancel filled order
        $cancelResult = $this->orderService->cancelOrder($order, $this->buyer);
        expect($cancelResult['success'])->toBeFalse();
    });
});

describe('Order Priority', function () {
    it('matches with earliest order first (FIFO)', function () {
        // Create two sell orders at the same price
        $sell1Result = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        // Create another seller
        $seller2 = User::factory()->create(['balance' => '0']);
        Asset::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000',
        ]);

        $sell2Result = $this->orderService->placeOrder($seller2, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        // Buyer's order should match with the first sell order
        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($buyResult['trade']->sell_order_id)->toBe($sell1Result['order']->id);

        // First sell order should be filled
        $sell1Result['order']->refresh();
        expect($sell1Result['order']->status)->toBe('filled');

        // Second sell order should still be open
        $sell2Result['order']->refresh();
        expect($sell2Result['order']->status)->toBe('open');
    });

    it('matches with best price first', function () {
        // Seller 1 sells at $50,000
        $sell1Result = $this->orderService->placeOrder($this->seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        // Seller 2 sells at $48,000 (better price for buyer)
        $seller2 = User::factory()->create(['balance' => '0']);
        Asset::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000',
        ]);

        $sell2Result = $this->orderService->placeOrder($seller2, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '48000.00000000',
            'amount' => '1.00000000',
        ]);

        // Buyer should match with the better price ($48,000)
        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '55000.00000000',
            'amount' => '1.00000000',
        ]);

        expect($buyResult['trade']->sell_order_id)->toBe($sell2Result['order']->id);
        expect($buyResult['trade']->price)->toBe('48000.00000000');
    });
});

describe('Self-Trade Prevention', function () {
    it('does not match user\'s own orders', function () {
        // User has both USD and BTC
        Asset::create([
            'user_id' => $this->buyer->id,
            'symbol' => 'BTC',
            'amount' => '5.00000000',
            'locked_amount' => '0.00000000',
        ]);

        // User places a sell order
        $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        // Same user places a matching buy order
        $buyResult = $this->orderService->placeOrder($this->buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        // Should not match with own order
        expect($buyResult['trade'])->toBeNull();
        expect($buyResult['order']->status)->toBe('open');
    });
});
