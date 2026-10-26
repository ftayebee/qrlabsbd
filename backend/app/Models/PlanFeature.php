<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    protected $fillable = ['key', 'label', 'type', 'value', 'is_highlighted', 'sort_order'];

    protected function casts(): array
    {
        return ['is_highlighted' => 'boolean', 'sort_order' => 'integer'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function typedValue(): int|bool|string
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'limit' => (int) $this->value,
            default => $this->value,
        };
    }
}
