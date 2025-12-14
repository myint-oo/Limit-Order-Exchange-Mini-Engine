<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a new order is created and added to the order book.
 * 
 * This event is broadcast to a public channel so all users can update their order book.
 */
class OrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     * Public channel for order book updates - no auth required.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orderbook.' . $this->order->symbol),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'symbol' => $this->order->symbol,
                'side' => $this->order->side,
                'price' => $this->order->price,
                'amount' => $this->order->amount,
                'status' => $this->order->status,
                'created_at' => $this->order->created_at->toISOString(),
            ],
        ];
    }
}
