<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'buyer_name' => $this->buyer?->name ?? 'Unknown',
            'seller_name' => $this->seller?->name ?? 'Unknown',
            'buy_order_id' => $this->buy_order_id,
            'sell_order_id' => $this->sell_order_id,
            'symbol' => $this->symbol,
            'price' => $this->price,
            'amount' => $this->amount,
            'total' => $this->total,
            'fee' => $this->fee,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
