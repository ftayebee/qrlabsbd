<?php

namespace App\Services\Qr;

use App\Enums\QrMode;
use App\Enums\QrStatus;
use App\Enums\QrType;
use App\Exceptions\PlanLimitException;
use App\Models\QrCode;
use App\Models\QrDestinationChange;
use App\Models\QrFile;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QrCodeService
{
    private const SHORT_CODE_ALPHABET = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly PayloadEncoder $encoder,
        private readonly EntitlementService $entitlements,
    ) {}

    /** @param array<string, mixed> $data Validated by StoreQrCodeRequest. */
    public function create(User $user, array $data): QrCode
    {
        $type = QrType::from($data['type']);
        $mode = QrMode::from($data['mode']);
        $e = $this->entitlements->for($user);

        if ($mode === QrMode::Dynamic && ! $e->withinLimit('dynamic_codes', $this->entitlements->dynamicCount($user))) {
            throw new PlanLimitException('dynamic_codes', 'You have reached the dynamic QR code limit for your plan.');
        }
        if ($mode === QrMode::Static && ! $e->withinLimit('saved_static_codes', $this->entitlements->savedStaticCount($user))) {
            throw new PlanLimitException('saved_static_codes', 'You have reached the saved static QR code limit for your plan.');
        }
        $this->assertDesignAllowed($user, $data['design'] ?? []);
        if (! empty($data['campaign_id']) && ! $e->allows('campaigns')) {
            throw new PlanLimitException('campaigns', 'Campaigns are available on paid plans.');
        }

        return DB::transaction(function () use ($user, $data, $type, $mode) {
            $qr = new QrCode([
                'name' => $data['name'],
                'type' => $type,
                'mode' => $mode,
                'status' => QrStatus::Active,
                'content' => $data['content'],
                'folder_id' => $data['folder_id'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
            ]);
            $qr->user()->associate($user);
            $qr->short_code = $mode === QrMode::Dynamic ? $this->uniqueShortCode() : null;
            $qr->static_payload = $mode === QrMode::Static ? $this->encoder->encode($type, $data['content']) : null;
            $qr->save();
            // Clears any cached "missing" lookup for this freshly issued code.
            $this->forgetCache($qr);

            $this->syncDestinations($qr, $data['content']);
            $this->attachFiles($qr, $data['content'], $data['design'] ?? []);
            $qr->design()->create(['config' => $this->cleanDesign($data['design'] ?? []), 'logo_file_id' => $data['design']['logoFileId'] ?? null]);
            $this->logChange($qr, $user, 'Created');

            return $qr->fresh(['design', 'destinations', 'campaign', 'folder']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(QrCode $qr, User $actor, array $data): QrCode
    {
        $contentChanged = array_key_exists('content', $data) && $data['content'] !== $qr->content;

        if ($contentChanged && $qr->isDynamic() && $this->entitlements->isOverDynamicLimit($qr->user)) {
            throw new PlanLimitException('dynamic_codes', 'Your plan’s dynamic code limit is exceeded, so destinations are read-only. Your codes keep working; upgrade or archive codes to edit them.');
        }
        if (isset($data['design'])) {
            $this->assertDesignAllowed($qr->user, $data['design']);
        }
        if (! empty($data['campaign_id']) && $data['campaign_id'] !== $qr->campaign_id && ! $this->entitlements->for($qr->user)->allows('campaigns')) {
            throw new PlanLimitException('campaigns', 'Campaigns are available on paid plans.');
        }

        return DB::transaction(function () use ($qr, $actor, $data, $contentChanged) {
            $qr->fill(collect($data)->only(['name', 'folder_id', 'campaign_id'])->all());
            if ($contentChanged) {
                $qr->content = $data['content'];
                if (! $qr->isDynamic()) {
                    $qr->static_payload = $this->encoder->encode($qr->type, $data['content']);
                }
            }
            $qr->save();

            if ($contentChanged) {
                $this->syncDestinations($qr, $data['content']);
                $this->attachFiles($qr, $data['content'], $data['design'] ?? []);
                $this->logChange($qr, $actor, $qr->isDynamic() ? 'Destination updated' : 'Content updated');
            }
            if (isset($data['design'])) {
                $qr->design()->updateOrCreate([], ['config' => $this->cleanDesign($data['design']), 'logo_file_id' => $data['design']['logoFileId'] ?? null]);
                $this->attachFiles($qr, [], $data['design']);
            }
            $this->forgetCache($qr);

            return $qr->fresh(['design', 'destinations', 'campaign', 'folder']);
        });
    }

    public function setStatus(QrCode $qr, User $actor, QrStatus $status): QrCode
    {
        if ($status === QrStatus::Active && $qr->status === QrStatus::Archived && $qr->isDynamic()) {
            $e = $this->entitlements->for($qr->user);
            if (! $e->withinLimit('dynamic_codes', $this->entitlements->dynamicCount($qr->user))) {
                throw new PlanLimitException('dynamic_codes', 'Restoring this code would exceed your plan’s dynamic code limit.');
            }
        }
        $qr->status = $status;
        $qr->save();
        $this->logChange($qr, $actor, 'Status changed to '.$status->value);
        $this->forgetCache($qr);

        return $qr;
    }

    /** Copies content and design into a new code with its own short link. Uploaded files are shared, not copied. */
    public function duplicate(QrCode $qr): QrCode
    {
        return $this->create($qr->user, [
            'name' => Str::limit($qr->name.' (copy)', 150, ''),
            'type' => $qr->type->value,
            'mode' => $qr->mode->value,
            'content' => $qr->content,
            'design' => $qr->design?->config ?? [],
            'folder_id' => $qr->folder_id,
            'campaign_id' => $qr->campaign_id,
        ]);
    }

    /** Soft-deletes. The short code stays reserved so a printed code can never be reassigned to someone else. */
    public function delete(QrCode $qr): void
    {
        $this->forgetCache($qr);
        $qr->delete();
    }

    public function forgetCache(QrCode $qr): void
    {
        if ($qr->short_code) {
            Cache::forget($qr->cacheKey());
        }
    }

    private function uniqueShortCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 7; $i++) {
                $code .= self::SHORT_CODE_ALPHABET[random_int(0, strlen(self::SHORT_CODE_ALPHABET) - 1)];
            }
        } while (QrCode::withTrashed()->where('short_code', $code)->exists());

        return $code;
    }

    /** @param array<string, mixed> $content */
    private function syncDestinations(QrCode $qr, array $content): void
    {
        $rows = match ($qr->type) {
            QrType::Url => [['kind' => 'primary', 'url' => PayloadEncoder::normalizeUrl((string) $content['url'])]],
            QrType::App => array_values(array_filter([
                ! empty($content['iosUrl']) ? ['kind' => 'ios', 'url' => PayloadEncoder::normalizeUrl($content['iosUrl'])] : null,
                ! empty($content['androidUrl']) ? ['kind' => 'android', 'url' => PayloadEncoder::normalizeUrl($content['androidUrl'])] : null,
                ! empty($content['fallbackUrl']) ? ['kind' => 'fallback', 'url' => PayloadEncoder::normalizeUrl($content['fallbackUrl'])] : null,
            ])),
            QrType::MultiUrl, QrType::BusinessCard => array_map(
                fn ($l) => ['kind' => 'link', 'label' => $l['label'] ?? null, 'url' => PayloadEncoder::normalizeUrl((string) $l['url'])],
                array_values(array_filter($content['links'] ?? [], fn ($l) => ! empty($l['url']))),
            ),
            QrType::Social => array_map(
                fn ($l) => ['kind' => 'social', 'platform' => $l['platform'], 'url' => PayloadEncoder::normalizeUrl((string) $l['url'])],
                $content['links'] ?? [],
            ),
            default => [],
        };

        $qr->destinations()->delete();
        foreach ($rows as $i => $row) {
            $qr->destinations()->create($row + ['position' => $i]);
        }
    }

    /**
     * Links uploaded files to this code. Files must belong to the code's owner.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $design
     */
    private function attachFiles(QrCode $qr, array $content, array $design): void
    {
        $ids = array_filter([$content['fileId'] ?? null, $design['logoFileId'] ?? null]);
        if ($ids) {
            QrFile::query()->where('user_id', $qr->user_id)->whereIn('id', $ids)->whereNull('qr_code_id')->update(['qr_code_id' => $qr->id]);
        }
    }

    /** @param array<string, mixed> $design */
    private function assertDesignAllowed(User $user, array $design): void
    {
        if (! empty($design['logoFileId']) && ! $this->entitlements->for($user)->allows('custom_logo')) {
            throw new PlanLimitException('custom_logo', 'Logos are available on paid plans.');
        }
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    private function cleanDesign(array $design): array
    {
        if (! empty($design['logoFileId'])) {
            $file = QrFile::query()->find($design['logoFileId']);
            $design['logoUrl'] = $file?->publicUrl();
        } else {
            $design['logoUrl'] = null;
        }

        return $design;
    }

    private function logChange(QrCode $qr, User $actor, string $summary): void
    {
        QrDestinationChange::query()->create(['qr_code_id' => $qr->id, 'user_id' => $actor->id, 'summary' => $summary, 'created_at' => now()]);
    }
}
