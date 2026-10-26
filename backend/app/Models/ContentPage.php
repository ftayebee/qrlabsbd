<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    protected $fillable = ['slug', 'type', 'title', 'body', 'meta_title', 'meta_description', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
