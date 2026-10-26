<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'providerPaymentId' => $this->provider_payment_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'refundedAmount' => $this->refunded_amount,
            'failureReason' => $this->failure_reason,
            'attempt' => $this->attempt,
            'paidAt' => $this->paid_at?->toIso8601String(),
            'invoiceNumber' => $this->whenLoaded('invoice', fn () => $this->invoice?->number),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email]),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
