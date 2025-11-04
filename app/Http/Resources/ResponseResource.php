<?php

namespace App\Http\Resources;

use App\Http\Resources\ResponseResource;

class ResponseResource
{
    public static function success($data = null, string $message = '', int $code = 200)
    {
        return response()->json([
            'code' => $code,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => null,
        ], $code);
    }

    public static function error($data = null, string $message = '', int $code = 500)
    {
        return response()->json([
            'code' => $code,
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'meta' => null,
        ], $code);
    }

    public static function json(int $code, string $status, string $message, $data = null, $meta = null)
    {
        $isSuccess = strtolower($status) === 'success';

        if ($isSuccess) {
            return self::success($data, $message, $code);
        }

        return self::error($data, $message, $code);
    }
}
