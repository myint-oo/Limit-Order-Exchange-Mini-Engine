<?php

namespace App\Services;

use App\Events\OrderMatched;
use App\Events\TradeExecuted;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * MatchingEngine handles order matching and trade execution.
 * 
 * - No partial fills (full order match only)
 * - First eligible order wins (FIFO by creation time)
 * - All operations are single transaction
 * - Commission rate: 1.5% of USD volume, paid by *seller*
 */
class MatchingEngine
{
    private const COMMISSION_RATE = '0.015'; // 1.5%

    public function __construct(
        private readonly WalletService $walletService
    ) {}

    /**
     * Attempt to match a newly placed order.
     * 
     * @return Trade|null The executed trade if matched, null otherwise
     */
    public function match(Order $newOrder): ?Trade
    {
        return DB::transaction(function () use ($newOrder) {
            // Lock the new order to prevent double-matching
            $order = Order::where('id', $newOrder->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return null;
            }

            $counterOrder = $this->findCompatibleOrder($order);

            if (! $counterOrder) {
                return null;
            }

            return $this->executeTrade($order, $counterOrder);
        });
    }

    /**
     * Find a compatible counter-order for matching.
     * 
     * For BUY orders: Find SELL orders where sell.price <= buy.price (lowest first)
     * For SELL orders: Find BUY orders where buy.price >= sell.price (highest first)
     * 
     * Orders must have exact same amount (no partial fills).
     * First eligible order wins (oldest by created_at).
     */
    private function findCompatibleOrder(Order $order): ?Order
    {
        $query = Order::where('symbol', $order->symbol)
            ->where('amount', $order->amount) // Exact amount match only
            ->where('status', Order::STATUS_OPEN)
            ->where('user_id', '!=', $order->user_id) // ignore own orders
            ->lockForUpdate();

        if ($order->side === 'buy') {
            // Find sell orders with price <= buy price
            $query->where('side', Order::SIDE_SELL)
                ->where('price', '<=', $order->price)
                ->orderBy('price', 'asc') // Best price first
                ->orderBy('created_at', 'asc'); // FIFO for same price
        } else {
            // Find buy orders with price >= sell price
            $query->where('side', 'buy')
                ->where('price', '>=', $order->price)
                ->orderBy('price', 'desc') // Best price first
                ->orderBy('created_at', 'asc'); // FIFO for same price
        }

        return $query->first();
    }

    /**
     * Execute a trade between two matched orders.
     * 
     * Trade executes at the maker's price (the older order).
     * Commission is deducted from seller's proceeds.
     * 
     * @param Order $takerOrder The new order (taker)
     * @param Order $makerOrder The existing order (maker)
     * 
     * @return Trade The executed trade
     */
    private function executeTrade(Order $takerOrder, Order $makerOrder): Trade
    {
        // Determine which is buy and which is sell
        $buyOrder = $takerOrder->side === Order::SIDE_BUY ? $takerOrder : $makerOrder;
        $sellOrder = $takerOrder->side === Order::SIDE_SELL ? $takerOrder : $makerOrder;

        // Trade executes at maker's price (price improvement for taker)
        $executionPrice = $makerOrder->price;
        $amount = $takerOrder->amount;
        $usdVolume = bcmul($executionPrice, $amount, 8);
        $feeUsd = bcmul($usdVolume, self::COMMISSION_RATE, 8);

        // Get users
        $buyer = User::find($buyOrder->user_id);
        $seller = User::find($sellOrder->user_id);

        // Calculate if buyer gets refund (when buying at better price than limit)
        $buyerLockedUsd = $buyOrder->locked_funds;
        $buyerExcessUsd = bcsub($buyerLockedUsd, $usdVolume, 8);

        // Transfer USD from buyer's locked funds to seller (minus fee)
        $this->walletService->transferUsd($buyer, $seller, $usdVolume, $feeUsd);

        // Refund excess USD to buyer if they got price improvement
        if (bccomp($buyerExcessUsd, '0', 8) > 0) {
            $this->walletService->refundExcessUsd($buyer, $buyerExcessUsd);
        }

        // Transfer asset from seller's locked assets to buyer
        $this->walletService->transferAsset($seller, $buyer, $takerOrder->symbol, $amount);

        // Mark both orders as filled
        $buyOrder->status = Order::STATUS_FILLED;
        $buyOrder->locked_funds = '0';
        $buyOrder->save();

        $sellOrder->status = Order::STATUS_FILLED;
        $sellOrder->locked_funds = '0';
        $sellOrder->save();

        $trade = Trade::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'symbol' => $takerOrder->symbol,
            'price' => $executionPrice,
            'amount' => $amount,
            'total' => $usdVolume,
            'fee' => $feeUsd,
        ]);

        // Dispatch event for real-time updates (after transaction commits)
        // The event will be dispatched via afterCommit callback
        $this->dispatchTradeEvent($trade, $buyer, $seller, $buyOrder, $sellOrder);

        // Dispatch public event for order book updates
        $this->dispatchTradeExecutedEvent($trade, $buyOrder->id, $sellOrder->id);

        return $trade;
    }

    /**
     * Dispatch trade matched event for real-time updates to buyer/seller.
     */
    private function dispatchTradeEvent(
        Trade $trade,
        User $buyer,
        User $seller,
        Order $buyOrder,
        Order $sellOrder
    ): void {
        // Only dispatch event if broadcasting is properly configured
        if (!config('broadcasting.connections.pusher.key')) {
            return;
        }

        // afterCommit to ensure event fires only after successful transaction
        DB::afterCommit(function () use ($trade, $buyer, $seller, $buyOrder, $sellOrder) {
            OrderMatched::dispatch($trade, $buyer, $seller, $buyOrder, $sellOrder);
        });
    }

    /**
     * Dispatch trade executed event for public order book updates.
     */
    private function dispatchTradeExecutedEvent(Trade $trade, int $buyOrderId, int $sellOrderId): void
    {
        if (!config('broadcasting.connections.pusher.key')) {
            return;
        }

        DB::afterCommit(function () use ($trade, $buyOrderId, $sellOrderId) {
            TradeExecuted::dispatch($trade, $buyOrderId, $sellOrderId);
        });
    }

    /**
     * Get commission rate as decimal string.
     */
    public function getCommissionRate(): string
    {
        return self::COMMISSION_RATE;
    }

    /**
     * Calculate commission for a given USD volume.
     */
    public function calculateCommission(string $usdVolume): string
    {
        return bcmul($usdVolume, self::COMMISSION_RATE, 8);
    }
}
