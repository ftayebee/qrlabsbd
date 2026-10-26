<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'periodStart' => $this->period_start?->toIso8601String(),
            'periodEnd' => $this->period_end?->toIso8601String(),
            'issuedAt' => $this->issued_at?->toIso8601String(),
            'paidAt' => $this->paid_at?->toIso8601String(),
            'hostedUrl' => $this->hosted_url,
            'pdfUrl' => $this->pdf_url,
        ];
    }
}
