<?php

namespace App\Http\Controllers;

use App\Http\Resources\TradeResource;
use App\Models\Trade;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class TradeController extends Controller
{
    public function getUserTrades(Request $request): JsonResponse
    {
        $user = $request->user();

        $trades = Trade::with(['buyer', 'seller'])
            ->where('buyer_id', $user->id)
            ->orWhere('seller_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => TradeResource::collection($trades),
            'meta' => [
                'current_page' => $trades->currentPage(),
                'last_page' => $trades->lastPage(),
                'per_page' => $trades->perPage(),
                'total' => $trades->total(),
            ],
        ]);
    }
}
