<?php

namespace App\Http\Resources;

use App\Enums\QrType;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QrCode */
class QrCodeResource extends JsonResource
{
    private ?string $readOnlyReason = null;

    public function readOnly(?string $reason): static
    {
        $this->readOnlyReason = $reason;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $content = $this->content;
        $single = $request->routeIs('qr-codes.show', 'qr-codes.update', 'qr-codes.store', 'qr-codes.duplicate');
        // Wi-Fi passwords are only returned for a single code the user opened, never in lists.
        $redact = $this->type === QrType::Wifi && ! $single;
        if ($redact) {
            unset($content['password']);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'shortCode' => $this->short_code,
            'shortUrl' => $this->shortUrl(),
            'encodedValue' => $redact ? null : $this->encodedValue(),
            'content' => $content,
            'design' => $this->whenLoaded('design', fn () => $this->design?->config),
            'folderId' => $this->folder_id,
            'campaignId' => $this->campaign_id,
            'folder' => $this->whenLoaded('folder', fn () => $this->folder ? ['id' => $this->folder->id, 'name' => $this->folder->name] : null),
            'campaign' => $this->whenLoaded('campaign', fn () => $this->campaign ? ['id' => $this->campaign->id, 'name' => $this->campaign->name, 'color' => $this->campaign->color] : null),
            'totalScans' => $this->total_scans,
            'uniqueScans' => $this->unique_scans,
            'lastScannedAt' => $this->last_scanned_at?->toIso8601String(),
            'moderationStatus' => $this->moderation_status,
            'canEdit' => ($request->user()?->can('update', $this->resource) ?? false) && $this->readOnlyReason === null,
            'readOnlyReason' => $this->readOnlyReason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
