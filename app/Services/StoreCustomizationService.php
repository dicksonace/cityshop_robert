<?php

namespace App\Services;

use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use Illuminate\Support\Arr;

class StoreCustomizationService
{
    public static function defaults(): array
    {
        return config('store-customization.defaults');
    }

    public static function presets(): array
    {
        return config('store-customization.presets');
    }

    public function forProfile(SellerProfile $profile): StoreCustomization
    {
        return $profile->storeCustomization ?? $profile->storeCustomization()->create([
            'published_settings' => self::defaults(),
            'draft_settings' => self::defaults(),
        ]);
    }

    public function publishedSettings(StoreCustomization $customization): array
    {
        return $this->mergeWithDefaults($customization->published_settings ?? []);
    }

    public function draftSettings(StoreCustomization $customization): array
    {
        return $this->mergeWithDefaults($customization->draft_settings ?? []);
    }

    public function mergeWithDefaults(array $settings): array
    {
        return array_replace_recursive(self::defaults(), $settings);
    }

    public function applyPreset(array $settings, string $presetKey): array
    {
        $preset = self::presets()[$presetKey] ?? null;

        if (! $preset) {
            return $settings;
        }

        $settings['theme']['preset'] = $presetKey;
        $settings['theme']['primary_color'] = $preset['primary_color'];
        $settings['theme']['secondary_color'] = $preset['secondary_color'];
        $settings['theme']['background_color'] = $preset['background_color'];
        $settings['theme']['text_color'] = $preset['text_color'];

        return $settings;
    }

    public function updateDraft(StoreCustomization $customization, array $incoming): StoreCustomization
    {
        $merged = $this->mergeWithDefaults(array_replace_recursive(
            $customization->draft_settings ?? [],
            $incoming,
        ));

        if (! empty($incoming['theme']['preset'])) {
            $merged = $this->applyPreset($merged, $incoming['theme']['preset']);
        }

        $customization->update(['draft_settings' => $merged]);

        return $customization->fresh();
    }

    public function publish(StoreCustomization $customization): StoreCustomization
    {
        $customization->update([
            'published_settings' => $this->draftSettings($customization),
            'published_at' => now(),
        ]);

        return $customization->fresh();
    }

    public function reset(StoreCustomization $customization): StoreCustomization
    {
        $defaults = self::defaults();

        $customization->update([
            'draft_settings' => $defaults,
            'published_settings' => $defaults,
            'published_at' => now(),
        ]);

        return $customization->fresh();
    }

    public function completeSetup(StoreCustomization $customization): StoreCustomization
    {
        if (! $customization->published_at) {
            $this->publish($customization);
        }

        $customization->update(['setup_completed_at' => now()]);

        return $customization->fresh();
    }

    public function syncBrandingToProfile(SellerProfile $profile, array $settings): void
    {
        $branding = $settings['branding'] ?? [];
        $updates = [];

        if (array_key_exists('description', $branding)) {
            $updates['store_description'] = $branding['description'] ?: null;
        }

        if (! empty($branding['store_logo'])) {
            $updates['shop_photo'] = $branding['store_logo'];
        }

        if ($updates !== []) {
            $profile->update($updates);
        }
    }

    public function isPromoActive(array $settings): bool
    {
        $promo = $settings['promo_banner'] ?? [];

        if (! ($promo['enabled'] ?? false)) {
            return false;
        }

        $now = now();

        if (! empty($promo['starts_at']) && $now->lt($promo['starts_at'])) {
            return false;
        }

        if (! empty($promo['ends_at']) && $now->gt($promo['ends_at'])) {
            return false;
        }

        return true;
    }

    public function orderedSections(array $settings): array
    {
        $order = Arr::get($settings, 'sections.order', []);
        $enabled = Arr::get($settings, 'sections.enabled', []);

        return array_values(array_filter($order, function (string $section) use ($enabled) {
            if (in_array($section, ['hero', 'products'], true)) {
                return true;
            }

            return (bool) ($enabled[$section] ?? false);
        }));
    }
}
