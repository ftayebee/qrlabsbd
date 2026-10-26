<?php

namespace Database\Seeders;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Starting catalogue. Prices are placeholders for the business to set in the
 * admin panel; the seeder never overwrites a plan that already exists.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'plan' => ['slug' => 'free', 'name' => 'Free', 'description' => 'Static codes and a taste of dynamic tracking.', 'is_default' => true, 'sort_order' => 0, 'cta_label' => 'Start free'],
                'prices' => [],
                'features' => [
                    ['dynamic_codes', 'Dynamic QR codes', 'limit', '2', true],
                    ['saved_static_codes', 'Saved static codes', 'limit', '25', false],
                    ['scans_per_month', 'Tracked scans per month', 'limit', '1000', false],
                    ['analytics_days', 'Analytics history (days)', 'limit', '30', true],
                    ['storage_mb', 'File storage (MB)', 'limit', '10', false],
                    ['max_upload_mb', 'Max PDF size (MB)', 'limit', '5', false],
                    ['team_members', 'Team members', 'limit', '0', false],
                    ['campaigns', 'Campaigns', 'boolean', 'false', false],
                    ['custom_logo', 'Logo in QR code', 'boolean', 'false', false],
                    ['vector_downloads', 'SVG and PDF downloads', 'boolean', 'true', true],
                    ['csv_export', 'CSV exports', 'boolean', 'false', false],
                ],
            ],
            [
                'plan' => ['slug' => 'pro', 'name' => 'Pro', 'description' => 'For creators and small businesses running real campaigns.', 'highlight' => true, 'trial_days' => 14, 'sort_order' => 1, 'cta_label' => 'Start 14-day trial'],
                'prices' => [['month', 'USD', 1200], ['year', 'USD', 11500]],
                'features' => [
                    ['dynamic_codes', 'Dynamic QR codes', 'limit', '50', true],
                    ['saved_static_codes', 'Saved static codes', 'limit', '-1', false],
                    ['scans_per_month', 'Tracked scans per month', 'limit', '50000', true],
                    ['analytics_days', 'Analytics history (days)', 'limit', '365', true],
                    ['storage_mb', 'File storage (MB)', 'limit', '1024', false],
                    ['max_upload_mb', 'Max PDF size (MB)', 'limit', '20', false],
                    ['team_members', 'Team members', 'limit', '2', false],
                    ['campaigns', 'Campaigns', 'boolean', 'true', true],
                    ['custom_logo', 'Logo in QR code', 'boolean', 'true', true],
                    ['vector_downloads', 'SVG and PDF downloads', 'boolean', 'true', false],
                    ['csv_export', 'CSV exports', 'boolean', 'true', false],
                ],
            ],
            [
                'plan' => ['slug' => 'business', 'name' => 'Business', 'description' => 'For teams managing codes across locations and brands.', 'trial_days' => 14, 'sort_order' => 2, 'cta_label' => 'Start 14-day trial'],
                'prices' => [['month', 'USD', 3900], ['year', 'USD', 37400]],
                'features' => [
                    ['dynamic_codes', 'Dynamic QR codes', 'limit', '500', true],
                    ['saved_static_codes', 'Saved static codes', 'limit', '-1', false],
                    ['scans_per_month', 'Tracked scans per month', 'limit', '500000', true],
                    ['analytics_days', 'Analytics history (days)', 'limit', '730', true],
                    ['storage_mb', 'File storage (MB)', 'limit', '10240', false],
                    ['max_upload_mb', 'Max PDF size (MB)', 'limit', '50', false],
                    ['team_members', 'Team members', 'limit', '10', true],
                    ['campaigns', 'Campaigns', 'boolean', 'true', false],
                    ['custom_logo', 'Logo in QR code', 'boolean', 'true', false],
                    ['vector_downloads', 'SVG and PDF downloads', 'boolean', 'true', false],
                    ['csv_export', 'CSV exports', 'boolean', 'true', true],
                ],
            ],
            [
                'plan' => ['slug' => 'enterprise', 'name' => 'Enterprise', 'description' => 'Custom limits, invoicing and onboarding for large organisations.', 'is_custom' => true, 'sort_order' => 3, 'cta_label' => 'Contact sales'],
                'prices' => [],
                'features' => [
                    ['dynamic_codes', 'Dynamic QR codes', 'text', 'Custom', true],
                    ['team_members', 'Team members', 'text', 'Custom', true],
                    ['analytics_days', 'Analytics history', 'text', 'Custom retention', true],
                    ['support', 'Priority support and onboarding', 'boolean', 'true', true],
                ],
            ],
        ];

        foreach ($plans as $def) {
            if (Plan::query()->where('slug', $def['plan']['slug'])->exists()) {
                continue;
            }
            $plan = Plan::query()->create($def['plan'] + ['status' => PlanStatus::Published, 'is_public' => true]);
            foreach ($def['prices'] as [$interval, $currency, $amount]) {
                $plan->prices()->create(['interval' => $interval, 'currency' => $currency, 'amount' => $amount, 'is_active' => true]);
            }
            foreach ($def['features'] as $i => [$key, $label, $type, $value, $highlight]) {
                $plan->features()->create(['key' => $key, 'label' => $label, 'type' => $type, 'value' => $value, 'is_highlighted' => $highlight, 'sort_order' => $i]);
            }
        }
    }
}
