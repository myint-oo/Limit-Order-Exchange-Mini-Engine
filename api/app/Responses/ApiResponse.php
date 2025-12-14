<?php

namespace App\Responses;

class ApiResponse 
{
    public static function success(
        $data = null, 
        string|null $message = null,
        int $statusCode = 200
    ) {
        $payload = [];
        
        if ($data) $payload['data'] = $data;

        if ($message) $payload['message'] = $message;

        return response()->json($payload, $statusCode);
    }
    
    public static function error(
        string $message = 'Something went wrong, please try again later',
        int $statusCode = 400,
        $errors = null
    ) {
        return response()->json([
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}