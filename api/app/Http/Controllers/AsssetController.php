<?php

namespace App\Http\Controllers;

use App\Http\Resources\AssetResource;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AsssetController extends Controller
{
    public function getUserAssets(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success(
            data: AssetResource::collection($user->assets)
        );
    }
}
