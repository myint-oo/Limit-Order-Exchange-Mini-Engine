<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when two orders are matched and a trade is executed.
 * 
 * This event is broadcast to both the buyer and seller's private channels
 * so they can update their UI in real-time.
 */
class OrderMatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Trade $trade;
    public User $buyer;
    public User $seller;
    public Order $buyOrder;
    public Order $sellOrder;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Trade $trade,
        User $buyer,
        User $seller,
        Order $buyOrder,
        Order $sellOrder
    ) {
        $this->trade = $trade;
        $this->buyer = $buyer;
        $this->seller = $seller;
        $this->buyOrder = $buyOrder;
        $this->sellOrder = $sellOrder;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->buyer->id),
            new PrivateChannel('user.' . $this->seller->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $freshBuyer = $this->buyer->fresh();
        $freshSeller = $this->seller->fresh();

        return [
            'trade' => [
                'id' => $this->trade->id,
                'symbol' => $this->trade->symbol,
                'price' => $this->trade->price,
                'amount' => $this->trade->amount,
                'total' => $this->trade->total,
                'fee' => $this->trade->fee,
                'buyer_id' => $this->trade->buyer_id,
                'seller_id' => $this->trade->seller_id,
                'buyer_name' => $freshBuyer->name,
                'seller_name' => $freshSeller->name,
                'created_at' => $this->trade->created_at->toIso8601String(),
            ],
            'buy_order' => [
                'id' => $this->buyOrder->id,
                'status' => $this->buyOrder->status,
                'user_id' => $this->buyOrder->user_id,
            ],
            'sell_order' => [
                'id' => $this->sellOrder->id,
                'status' => $this->sellOrder->status,
                'user_id' => $this->sellOrder->user_id,
            ],
            'buyer' => [
                'id' => $freshBuyer->id,
                'name' => $freshBuyer->name,
                'balance' => $freshBuyer->balance,
                'locked_balance' => $freshBuyer->locked_balance,
            ],
            'seller' => [
                'id' => $freshSeller->id,
                'name' => $freshSeller->name,
                'balance' => $freshSeller->balance,
                'locked_balance' => $freshSeller->locked_balance,
            ],
        ];
    }
}
