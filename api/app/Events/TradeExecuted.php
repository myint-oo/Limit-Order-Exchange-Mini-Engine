<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a trade is executed.
 * 
 * This event is broadcast to a public channel so all users can update their order book
 * (remove the matched orders).
 */
class TradeExecuted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Trade $trade;
    public int $buyOrderId;
    public int $sellOrderId;

    /**
     * Create a new event instance.
     */
    public function __construct(Trade $trade, int $buyOrderId, int $sellOrderId)
    {
        $this->trade = $trade;
        $this->buyOrderId = $buyOrderId;
        $this->sellOrderId = $sellOrderId;
    }

    /**
     * Get the channels the event should broadcast on.
     * Public channel for order book updates - no auth required.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orderbook.' . $this->trade->symbol),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'trade.executed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'trade' => [
                'id' => $this->trade->id,
                'symbol' => $this->trade->symbol,
                'price' => $this->trade->price,
                'amount' => $this->trade->amount,
                'total' => $this->trade->total,
                'created_at' => $this->trade->created_at->toISOString(),
            ],
            'buy_order_id' => $this->buyOrderId,
            'sell_order_id' => $this->sellOrderId,
        ];
    }
}
