<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Thrown when an action exceeds the user's plan entitlements. Rendered as HTTP 402. */
class PlanLimitException extends RuntimeException
{
    public function __construct(public readonly string $feature, string $message)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'plan_limit',
            'feature' => $this->feature,
        ], 402);
    }
}
