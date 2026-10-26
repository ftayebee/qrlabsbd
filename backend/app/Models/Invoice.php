<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'user_id', 'subscription_id', 'number', 'provider', 'provider_invoice_id', 'status', 'currency',
        'subtotal', 'discount', 'tax', 'total', 'period_start', 'period_end', 'issued_at', 'paid_at', 'hosted_url', 'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer', 'discount' => 'integer', 'tax' => 'integer', 'total' => 'integer',
            'period_start' => 'datetime', 'period_end' => 'datetime', 'issued_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
