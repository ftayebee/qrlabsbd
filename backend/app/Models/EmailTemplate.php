<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'body', 'variables', 'is_active'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'is_active' => 'boolean'];
    }

    /**
     * Replaces {{ variable }} placeholders. Values are escaped by the mail
     * renderer, so templates cannot inject markup through variables.
     *
     * @param  array<string, string>  $vars
     * @return array{subject: string, body: string}
     */
    public function render(array $vars): array
    {
        $replace = fn (string $text) => preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => $vars[$m[1]] ?? '', $text);

        return ['subject' => (string) $replace($this->subject), 'body' => (string) $replace($this->body)];
    }
}
