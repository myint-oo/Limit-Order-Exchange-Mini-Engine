<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TradeResource;
use App\Models\Order;
use App\Responses\ApiResponse;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) { }

    public function getUserOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');

        $query = $user->orders()->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10);

        return ApiResponse::success(
            data: [
                'data' => OrderResource::collection($orders),
                'meta' => [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'last_page' => $orders->lastPage(),
                    'current_page' => $orders->currentPage(),
                ],
            ]
        );
    }

    public function getOrderBook(Request $request): JsonResponse
    {
        $symbol = $request->query('symbol', 'BTC');

        $buyOrders = Order::where('symbol', $symbol)
            ->where('side', Order::SIDE_BUY)
            ->where('status', Order::STATUS_OPEN)
            ->orderBy('price', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();

        $sellOrders = Order::where('symbol', $symbol)
            ->where('side', Order::SIDE_SELL)
            ->where('status', Order::STATUS_OPEN)
            ->orderBy('price', 'asc')
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();

        return ApiResponse::success(
            data: [
                'symbol' => $symbol,
                'buy_orders' => OrderResource::collection($buyOrders),
                'sell_orders' => OrderResource::collection($sellOrders),
            ]
        );
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $result = $this->orderService->placeOrder($user, $data);

        if (! $result['success']) {
            return ApiResponse::error(
                $result['error'],
                statusCode: 422
            );
        }

        $response = [
            'message' => $result['trade'] 
                ? 'Order matched and executed immediately' 
                : 'Order placed successfully',
            'order' => new OrderResource($result['order']),
        ];

        if ($result['trade']) {
            $response['trade'] = new TradeResource($result['trade']);
        }

        return ApiResponse::success(
            data: $response,
            message: $response['message'],
            statusCode: 201
        );
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        $result = $this->orderService->cancelOrder($order, $user);

        if (!$result['success']) {
            $statusCode = $result['error'] === 'Unauthorized: You do not own this order' ? 403 : 422;
            return response()->json([
                'message' => $result['error'],
            ], $statusCode);
        }

        return ApiResponse::success(
            data: ['order' => new OrderResource($order->fresh())],
            message: 'Order cancelled successfully'
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return ApiResponse::success(
            data: ['order' => new OrderResource($order)]
        );
    }
}
