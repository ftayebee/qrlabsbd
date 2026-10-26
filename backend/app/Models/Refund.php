<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = ['payment_id', 'provider_refund_id', 'amount', 'currency', 'status', 'reason'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
