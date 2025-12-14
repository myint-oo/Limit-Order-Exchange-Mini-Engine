<?php

namespace App\Services;

use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * OrderService handles order placement, validation, and cancellation.
 */
class OrderService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly MatchingEngine $matchingEngine
    ) { }

    /**
     * @return array ['success' => bool, 'order' => Order|null, 'trade' => Trade|null, 'error' => string|null]
     */
    public function placeOrder(User $user, array $orderData): array
    {
        $symbol = $orderData['symbol'];
        $side = $orderData['side'];
        $price = (string) $orderData['price'];
        $amount = (string) $orderData['amount'];

        return DB::transaction(function () use ($user, $symbol, $side, $price, $amount) {
            if ($side === 'buy') {
                $result = $this->placeBuyOrder($user, $symbol, $price, $amount);
            } else {
                $result = $this->placeSellOrder($user, $symbol, $price, $amount);
            }

            if (! $result['success']) {
                return $result;
            }

            $order = $result['order'];

            // Attempt immediate matching
            $trade = $this->matchingEngine->match($order);

            // If no match, broadcast order created event for order book update
            if (!$trade) {
                $this->dispatchOrderCreatedEvent($order);
            }

            return [
                'success' => true,
                'order' => $order->fresh(),
                'trade' => $trade,
                'error' => null,
            ];
        });
    }

    /**
     * Dispatch order created event for real-time order book updates.
     */
    private function dispatchOrderCreatedEvent(Order $order): void
    {
        if (!config('broadcasting.connections.pusher.key')) {
            return;
        }

        DB::afterCommit(function () use ($order) {
            OrderCreated::dispatch($order);
        });
    }

    /**
     * Dispatch order cancelled event for real-time order book updates.
     */
    private function dispatchOrderCancelledEvent(Order $order): void
    {
        if (!config('broadcasting.connections.pusher.key')) {
            return;
        }

        DB::afterCommit(function () use ($order) {
            OrderCancelled::dispatch($order);
        });
    }

    private function placeBuyOrder(User $user, string $symbol, string $price, string $amount): array
    {
        // Calculate required USD (price * amount)
        $requiredUsd = bcmul($price, $amount, 8);

        if (! $this->walletService->lockUsdBalance($user, $requiredUsd)) {
            return [
                'success' => false,
                'order' => null,
                'trade' => null,
                'error' => 'Insufficient USD balance',
                'required' => $requiredUsd,
                'available' => $this->walletService->getAvailableUsdBalance($user),
            ];
        }

        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => $symbol,
            'side' => Order::SIDE_BUY,
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_OPEN,
            'locked_funds' => $requiredUsd,
        ]);

        return [
            'success' => true,
            'order' => $order,
            'trade' => null,
            'error' => null,
        ];
    }

    private function placeSellOrder(User $user, string $symbol, string $price, string $amount): array
    {
        // Lock asset from user's holdings
        if (! $this->walletService->lockAsset($user, $symbol, $amount)) {
            return [
                'success' => false,
                'order' => null,
                'trade' => null,
                'error' => "Insufficient {$symbol} balance",
                'required' => $amount,
                'available' => $this->walletService->getAvailableAssetBalance($user, $symbol),
            ];
        }

        // Create the order
        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => $symbol,
            'side' => Order::SIDE_SELL,
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_OPEN,
            'locked_funds' => $amount, // For sell orders, locked_funds is the asset amount
        ]);

        return [
            'success' => true,
            'order' => $order,
            'trade' => null,
            'error' => null,
        ];
    }

    /**
     * @param User $user The user requesting cancellation (for authorization)
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function cancelOrder(Order $order, User $user): array
    {
        // Authorization check
        if ($order->user_id !== $user->id) {
            return [
                'success' => false,
                'error' => 'Unauthorized: You do not own this order',
            ];
        }

        return DB::transaction(function () use ($order, $user) {
            // Lock the order row
            $lockedOrder = Order::where('id', $order->id)
                ->lockForUpdate()
                ->first();

            // Verify order is still cancellable
            if (!$lockedOrder || !in_array($lockedOrder->status, ['open', 'partial'])) {
                return [
                    'success' => false,
                    'error' => 'Order cannot be cancelled',
                ];
            }

            // Unlock funds
            if ($lockedOrder->side === 'buy') {
                $this->walletService->unlockUsdBalance($user, $lockedOrder->locked_funds);
            } else {
                $this->walletService->unlockAsset($user, $lockedOrder->symbol, $lockedOrder->locked_funds);
            }

            // Update order status
            $lockedOrder->status = 'cancelled';
            $lockedOrder->locked_funds = '0';
            $lockedOrder->save();

            // Dispatch event for real-time order book update
            $this->dispatchOrderCancelledEvent($lockedOrder);

            return [
                'success' => true,
                'error' => null,
            ];
        });
    }

    /**
     * Validate order data.
     * 
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateOrderData(array $data): array
    {
        $errors = [];

        // Symbol validation
        $validSymbols = ['BTC', 'ETH'];
        if (!isset($data['symbol']) || !in_array($data['symbol'], $validSymbols)) {
            $errors['symbol'] = 'Invalid symbol. Must be BTC or ETH.';
        }

        // Side validation
        if (!isset($data['side']) || !in_array($data['side'], ['buy', 'sell'])) {
            $errors['side'] = 'Invalid side. Must be buy or sell.';
        }

        // Price validation
        if (!isset($data['price']) || $data['price'] <= 0) {
            $errors['price'] = 'Price must be greater than 0.';
        }

        // Amount validation
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            $errors['amount'] = 'Amount must be greater than 0.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
