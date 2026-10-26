<?php

namespace App\Services\Billing\Gateway;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A provider call failed. The message is safe to show; provider details are logged separately. */
class GatewayException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'gateway_error'], 502);
    }
}
