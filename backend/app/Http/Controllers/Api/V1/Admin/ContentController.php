<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ContentPage;
use App\Models\EmailTemplate;
use App\Models\Faq;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FAQ, legal/landing page and email template management. Page bodies are
 * Markdown; the frontend renders them with HTML escaped, so stored content
 * cannot inject script.
 */
class ContentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function faqs(): JsonResponse
    {
        return response()->json(['data' => Faq::query()->orderBy('category')->orderBy('sort_order')->get()]);
    }

    public function storeFaq(Request $request): JsonResponse
    {
        $faq = Faq::query()->create($this->faqData($request));

        return response()->json(['data' => $faq], 201);
    }

    public function updateFaq(Request $request, Faq $faq): JsonResponse
    {
        $faq->update($this->faqData($request));

        return response()->json(['data' => $faq]);
    }

    public function destroyFaq(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json(['message' => 'FAQ deleted.']);
    }

    public function pages(): JsonResponse
    {
        return response()->json(['data' => ContentPage::query()->orderBy('type')->orderBy('slug')->get()]);
    }

    public function storePage(Request $request): JsonResponse
    {
        $page = ContentPage::query()->create($this->pageData($request));
        $this->audit->log($request->user(), 'content.page_created', $page);

        return response()->json(['data' => $page], 201);
    }

    public function updatePage(Request $request, ContentPage $page): JsonResponse
    {
        $page->update($this->pageData($request, $page));
        $this->audit->log($request->user(), 'content.page_updated', $page);

        return response()->json(['data' => $page]);
    }

    public function destroyPage(Request $request, ContentPage $page): JsonResponse
    {
        abort_if(in_array($page->slug, ['terms', 'privacy'], true), 422, 'Terms and privacy pages can be edited or unpublished, but not deleted.');
        $page->delete();
        $this->audit->log($request->user(), 'content.page_deleted', $page);

        return response()->json(['message' => 'Page deleted.']);
    }

    public function templates(): JsonResponse
    {
        return response()->json(['data' => EmailTemplate::query()->orderBy('key')->get()]);
    }

    public function updateTemplate(Request $request, EmailTemplate $template): JsonResponse
    {
        $template->update($request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'is_active' => ['boolean'],
        ]));
        $this->audit->log($request->user(), 'content.email_template_updated', $template);

        return response()->json(['data' => $template]);
    }

    public function messages(Request $request): JsonResponse
    {
        $messages = ContactMessage::query()->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest()->paginate(25);

        return response()->json([
            'data' => $messages->items(),
            'meta' => ['current_page' => $messages->currentPage(), 'last_page' => $messages->lastPage(), 'per_page' => $messages->perPage(), 'total' => $messages->total()],
        ]);
    }

    public function updateMessage(Request $request, ContactMessage $message): JsonResponse
    {
        $message->update($request->validate(['status' => ['required', Rule::in(['new', 'read', 'replied', 'spam'])]]));

        return response()->json(['data' => $message]);
    }

    /** @return array<string, mixed> */
    private function faqData(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['integer', 'between:0,1000'],
            'is_published' => ['boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function pageData(Request $request, ?ContentPage $page = null): array
    {
        return $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('content_pages')->ignore($page?->id)],
            'type' => ['required', Rule::in(['legal', 'landing'])],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'is_published' => ['boolean'],
        ]);
    }
}
