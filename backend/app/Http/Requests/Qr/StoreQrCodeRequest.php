<?php

namespace App\Http\Requests\Qr;

use App\Enums\QrMode;
use App\Enums\QrType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQrCodeRequest extends FormRequest
{
    use FiltersNestedInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = QrType::tryFrom((string) $this->input('type'));
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(QrType::class)],
            'mode' => ['required', Rule::enum(QrMode::class)],
            'content' => ['required', 'array'],
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('user_id', $userId)],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('user_id', $userId)],
            ...DesignRules::rules(),
            ...($type ? QrContentRules::for($type, $this->input('mode') === 'dynamic') : []),
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $type = QrType::tryFrom((string) $this->input('type'));
            $mode = QrMode::tryFrom((string) $this->input('mode'));
            if ($type && $mode && ! in_array($mode, $type->supportedModes(), true)) {
                $v->errors()->add('mode', "{$type->value} codes do not support {$mode->value} mode.");
            }
        }];
    }
}
