<?php

namespace App\Http\Requests\Qr;

use Illuminate\Validation\Rule;

class DesignRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $hex = ['regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'design' => ['sometimes', 'array'],
            'design.foreground' => ['required_with:design', ...$hex],
            'design.background' => ['required_with:design', ...$hex],
            'design.transparent' => ['boolean'],
            'design.errorCorrection' => ['required_with:design', Rule::in(['L', 'M', 'Q', 'H'])],
            'design.dotStyle' => ['required_with:design', Rule::in(['square', 'rounded', 'dots', 'classy', 'classy-rounded', 'extra-rounded'])],
            'design.cornerSquareStyle' => ['required_with:design', Rule::in(['square', 'dot', 'extra-rounded'])],
            'design.cornerDotStyle' => ['required_with:design', Rule::in(['square', 'dot'])],
            'design.cornerColor' => ['nullable', ...$hex],
            'design.logoSize' => ['numeric', 'between:0.1,0.3'],
            'design.margin' => ['integer', 'between:0,48'],
            'design.frame' => [Rule::in(['none', 'label-bottom', 'label-top', 'badge'])],
            'design.frameLabel' => ['nullable', 'string', 'max:28'],
            'design.frameColor' => ['nullable', ...$hex],
            'design.logoFileId' => ['nullable', 'integer', Rule::exists('qr_files', 'id')->where('user_id', request()->user()?->id)->where('purpose', 'logo')->whereNull('deleted_at')],
            // Ignored on save: the server derives logoUrl from logoFileId.
            'design.logoUrl' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
