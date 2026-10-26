<?php

namespace App\Http\Requests\Qr;

use App\Models\QrCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQrCodeRequest extends FormRequest
{
    use FiltersNestedInput;

    public function authorize(): bool
    {
        /** @var QrCode $qr */
        $qr = $this->route('qrCode');

        return $this->user()?->can('update', $qr) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var QrCode $qr */
        $qr = $this->route('qrCode');
        $ownerId = $qr->user_id;

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'content' => ['sometimes', 'array'],
            'folder_id' => ['sometimes', 'nullable', 'integer', Rule::exists('folders', 'id')->where('user_id', $ownerId)],
            'campaign_id' => ['sometimes', 'nullable', 'integer', Rule::exists('campaigns', 'id')->where('user_id', $ownerId)],
            ...DesignRules::rules(),
            ...($this->has('content') ? QrContentRules::for($qr->type, $qr->isDynamic()) : []),
        ];
    }
}
