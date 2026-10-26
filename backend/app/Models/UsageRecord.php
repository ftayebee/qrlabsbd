<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    protected $fillable = ['user_id', 'metric', 'period', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
