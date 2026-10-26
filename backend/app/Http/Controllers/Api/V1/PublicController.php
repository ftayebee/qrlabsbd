<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QrMode;
use App\Enums\QrStatus;
use App\Enums\QrType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\AbuseReport;
use App\Models\ContactMessage;
use App\Models\ContentPage;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\QrDestination;
use App\Models\QrFile;
use App\Services\Qr\PayloadEncoder;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PublicController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = Cache::remember('public:plans', 300, fn () => PlanResource::collection(
            Plan::query()->publiclyListed()->with(['prices', 'features'])->get()
        )->resolve());

        return response()->json(['data' => $plans])->header('Cache-Control', 'public, max-age=60');
    }

    public function settings(SettingsService $settings): JsonResponse
    {
        return response()->json(['data' => $settings->public()])->header('Cache-Control', 'public, max-age=60');
    }

    public function faqs(Request $request): JsonResponse
    {
        $faqs = Faq::query()->where('is_published', true)
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderBy('sort_order')->get(['id', 'question', 'answer', 'category']);

        return response()->json(['data' => $faqs])->header('Cache-Control', 'public, max-age=60');
    }

    public function page(string $slug): JsonResponse
    {
        $page = ContentPage::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return response()->json(['data' => [
            'slug' => $page->slug, 'type' => $page->type, 'title' => $page->title, 'body' => $page->body,
            'metaTitle' => $page->meta_title, 'metaDescription' => $page->meta_description, 'updatedAt' => $page->updated_at?->toIso8601String(),
        ]])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Data for a hosted landing page (/p/{code}). Only fields meant for the
     * public page are returned; owner and analytics data never are.
     */
    public function hostedPage(string $code): JsonResponse
    {
        $qr = $this->publicCode($code);
        $c = $qr->content;
        $links = $qr->destinations->map(fn (QrDestination $d) => ['label' => $d->label, 'url' => $d->url, 'platform' => $d->platform, 'kind' => $d->kind])->values();

        $data = match ($qr->type) {
            QrType::MultiUrl => ['title' => $c['title'] ?? '', 'description' => $c['description'] ?? null, 'links' => $links],
            QrType::Social => ['title' => $c['title'] ?? '', 'description' => $c['description'] ?? null, 'links' => $links],
            QrType::BusinessCard, QrType::Contact => collect($c)->only([
                'firstName', 'lastName', 'company', 'jobTitle', 'phone', 'mobile', 'email', 'website',
                'street', 'city', 'region', 'postcode', 'country', 'headline', 'bio', 'accentColor',
            ])->all() + ['links' => $qr->type === QrType::BusinessCard ? $links : []],
            QrType::App => ['name' => $c['name'] ?? '', 'description' => $c['description'] ?? null, 'iosUrl' => $c['iosUrl'] ?? null, 'androidUrl' => $c['androidUrl'] ?? null, 'fallbackUrl' => $c['fallbackUrl'] ?? null],
            QrType::Pdf => $this->pdfData($qr),
            default => abort(404),
        };

        return response()->json(['data' => ['type' => $qr->type->value, 'name' => $qr->name, 'code' => $qr->short_code, 'content' => $data]]);
    }

    public function vcard(string $code, PayloadEncoder $encoder): Response
    {
        $qr = $this->publicCode($code);
        abort_unless(in_array($qr->type, [QrType::Contact, QrType::BusinessCard], true), 404);
        $name = trim(($qr->content['firstName'] ?? 'contact').'-'.($qr->content['lastName'] ?? ''), '-');

        return response($encoder->vcard($qr->content), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9-]+/', '-', $name).'.vcf"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function report(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(['phishing', 'malware', 'spam', 'illegal', 'impersonation', 'other'])],
            'details' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);
        $qr = QrCode::query()->where('short_code', $code)->firstOrFail();
        AbuseReport::query()->create(['qr_code_id' => $qr->id, 'reason' => $data['reason'], 'details' => $data['details'] ?? null, 'reporter_email' => $data['email'] ?? null]);
        if (AbuseReport::query()->where('qr_code_id', $qr->id)->where('status', 'open')->count() >= 3 && $qr->moderation_status === 'clear') {
            $qr->update(['moderation_status' => 'flagged']);
        }

        return response()->json(['message' => 'Thank you. Our team will review this report.'], 201);
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'topic' => ['required', Rule::in(['sales', 'support', 'billing', 'privacy', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            // Honeypot: real users never fill this hidden field.
            'website' => ['prohibited'],
        ]);
        ContactMessage::query()->create($data);

        return response()->json(['message' => 'Thanks — we will reply within one business day.'], 201);
    }

    private function publicCode(string $code): QrCode
    {
        $qr = QrCode::query()->where('short_code', $code)->where('mode', QrMode::Dynamic)->with(['destinations', 'user:id,status'])->first();
        abort_if(! $qr || $qr->moderation_status === 'blocked' || $qr->user?->status === 'suspended', 404);
        abort_if($qr->status !== QrStatus::Active, 410, $qr->status === QrStatus::Paused ? 'This QR code is temporarily paused.' : 'This QR code is no longer active.');

        return $qr;
    }

    /** @return array<string, mixed> */
    private function pdfData(QrCode $qr): array
    {
        $file = QrFile::query()->where('user_id', $qr->user_id)->find($qr->content['fileId'] ?? 0);

        return [
            'title' => $qr->content['title'] ?? 'Document',
            'description' => $qr->content['description'] ?? null,
            'fileUrl' => $file?->publicUrl(),
            'fileName' => $file?->original_name,
            'fileSize' => $file?->size,
        ];
    }
}
