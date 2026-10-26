<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\EmailTemplate;
use App\Models\Faq;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/** Default settings, FAQ, legal pages and email templates. Existing rows are left untouched. */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['brand.name', 'brand', 'Qrio', true],
            ['brand.tagline', 'brand', 'Create, customise and track QR codes that make every scan count.', true],
            ['brand.support_email', 'brand', 'support@qrio.app', true],
            ['social.x', 'brand', '', true],
            ['social.linkedin', 'brand', '', true],
            ['social.instagram', 'brand', '', true],
            ['seo.default_title', 'seo', 'Qrio – Dynamic QR Code Generator with Analytics', true],
            ['seo.default_description', 'seo', 'Create static and dynamic QR codes for links, PDFs, Wi-Fi, vCards and more. Customise the design, edit destinations after printing and track scans.', true],
            ['landing.announcement', 'landing', '', true],
            ['security.blocked_hosts', 'security', [], false],
            ['billing.grace_days_note', 'billing', 'Lapsed accounts keep full access for 14 days. After that, existing dynamic codes keep redirecting but editing codes above the free limit is locked.', true],
        ];
        foreach ($settings as [$key, $group, $value, $public]) {
            SystemSetting::query()->firstOrCreate(['key' => $key], ['group' => $group, 'value' => $value, 'is_public' => $public]);
        }

        $faqs = [
            ['What is the difference between static and dynamic QR codes?', 'A static code stores its content directly in the pattern, so it can never change and cannot be tracked. A dynamic code stores a short link on our domain; you can change where it points at any time and see scan analytics, without reprinting.', 'basics'],
            ['Do I need an account to create a QR code?', 'No. Static codes for URLs, text, Wi-Fi, email, SMS, phone, vCard and app links are generated in your browser and can be downloaded without signing up. An account is needed for dynamic codes, hosted pages, PDFs and analytics.', 'basics'],
            ['Will my QR codes stop working if I cancel?', 'Static codes never expire. Dynamic codes keep redirecting after a paid plan ends. You keep full access for a 14-day grace period; after that, codes above the free plan limit become read-only until you upgrade or archive some codes.', 'billing'],
            ['How are unique scans counted?', 'Unique scans are an estimate. We count one visitor per code per day based on a salted, daily-rotating hash of the network address and browser. We never store IP addresses. Shared networks can undercount and switching networks can overcount.', 'analytics'],
            ['Do you track bots and link previews?', 'Known crawlers, link-preview bots and monitoring tools are detected by their user agent and excluded from scan totals. They are counted separately so you can see how many were filtered out.', 'analytics'],
            ['Which file formats can I download?', 'PNG for everyday use, SVG for print and design tools, and PDF for print-ready files. Transparent backgrounds are supported in PNG and SVG.', 'design'],
            ['Can I add my logo?', 'Yes, on paid plans. Logos cover part of the code, so we automatically recommend higher error correction and warn you if the design is unlikely to scan reliably.', 'design'],
            ['Is my Wi-Fi password safe?', 'Wi-Fi codes are generated in your browser. If you save one to your library, the password is encrypted at rest and never written to analytics or logs.', 'security'],
            ['Which payment methods do you accept?', 'Payments are processed securely by our payment provider. Available methods depend on your country and currency and are shown at checkout.', 'billing'],
            ['Can I change plans at any time?', 'Yes. Upgrades take effect immediately with a prorated charge. Downgrades to a lower paid plan are prorated; downgrading to Free happens at the end of your billing period.', 'billing'],
        ];
        if (! Faq::query()->exists()) {
            foreach ($faqs as $i => [$q, $a, $cat]) {
                Faq::query()->create(['question' => $q, 'answer' => $a, 'category' => $cat, 'sort_order' => $i, 'is_published' => true]);
            }
        }

        $pages = [
            ['terms', 'Terms of Service', "## Agreement\n\nBy using Qrio you agree to these terms. Replace this text with terms reviewed by your legal counsel before launch.\n\n## Acceptable use\n\nYou may not use Qrio to distribute malware, phishing, illegal content or to impersonate others. We may disable codes that breach these rules.\n\n## Service changes\n\nWe may update features and these terms. Material changes will be notified by email."],
            ['privacy', 'Privacy Policy', "## What we collect\n\nAccount details you provide, the QR content you choose to save, and privacy-reduced scan analytics (time, coarse location from your CDN, device and browser category, referrer domain).\n\n## What we do not collect\n\nWe do not store IP addresses of people who scan codes. Unique-scan estimates use a salted hash that rotates daily.\n\n## Retention\n\nRaw scan events are deleted after 90 days; daily aggregates are kept for reporting.\n\nReplace this text with a policy reviewed by your legal counsel before launch."],
            ['cookies', 'Cookie Policy', "## Essential cookies\n\nWe use a session cookie and a CSRF protection cookie to keep you signed in securely. We do not use advertising cookies."],
            ['acceptable-use', 'Acceptable Use Policy', "## Prohibited content\n\nPhishing, malware, scams, illegal goods, harassment and impersonation are not allowed. Report a code from its landing page or contact support."],
        ];
        foreach ($pages as [$slug, $title, $body]) {
            ContentPage::query()->firstOrCreate(['slug' => $slug], ['type' => 'legal', 'title' => $title, 'body' => $body, 'is_published' => true]);
        }

        $templates = [
            ['verify_email', 'Email verification', 'Verify your email for {{ app_name }}', "Hi {{ name }},\n\nPlease confirm your email address to finish setting up your {{ app_name }} account. This link expires in 60 minutes.\n\nIf you did not create an account, you can ignore this email.", ['name', 'app_name']],
            ['reset_password', 'Password reset', 'Reset your {{ app_name }} password', "Hi {{ name }},\n\nWe received a request to reset your password. This link expires in 60 minutes.\n\nIf you did not request a reset, no action is needed.", ['name', 'app_name']],
            ['payment_failed', 'Payment failed', 'Payment failed for your {{ app_name }} subscription', "Hi {{ name }},\n\nWe could not process your payment of {{ amount }}. We will retry automatically over the next few days, and your QR codes keep working in the meantime.\n\nPlease update your payment method to avoid losing premium features.", ['name', 'amount', 'app_name']],
            ['team_invite', 'Team invitation', '{{ inviter }} invited you to {{ app_name }}', "{{ inviter }} has invited you to help manage their QR codes on {{ app_name }}.\n\nSign in or create an account with this email address, then accept the invitation.", ['inviter', 'app_name']],
        ];
        foreach ($templates as [$key, $name, $subject, $body, $vars]) {
            EmailTemplate::query()->firstOrCreate(['key' => $key], ['name' => $name, 'subject' => $subject, 'body' => $body, 'variables' => $vars, 'is_active' => true]);
        }
    }
}
