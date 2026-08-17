<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Services\StoreCustomizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function __construct(private StoreCustomizationService $customizations) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function update(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $this->customizations->forProfile($profile);
        $draft = $this->customizations->draftSettings($customization);

        $validated = $request->validate([
            'store_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'slogan' => ['nullable', 'string', 'max:160'],
            'preset' => ['nullable', 'string', 'in:'.implode(',', array_keys(StoreCustomizationService::presets()))],
            'announcement_enabled' => ['sometimes', 'boolean'],
            'announcement_text' => ['nullable', 'string', 'max:240'],
            'promo_enabled' => ['sometimes', 'boolean'],
            'promo_text' => ['nullable', 'string', 'max:240'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'hero_autoplay_seconds' => ['nullable', 'integer', 'min:2', 'max:20'],
            'sections_enabled' => ['nullable', 'array'],
            'sections_enabled.announcement' => ['sometimes', 'boolean'],
            'sections_enabled.promo' => ['sometimes', 'boolean'],
            'sections_enabled.featured' => ['sometimes', 'boolean'],
            'sections_enabled.about' => ['sometimes', 'boolean'],
            'sections_enabled.contact' => ['sometimes', 'boolean'],
            'remove_hero_paths' => ['nullable', 'array'],
            'remove_hero_paths.*' => ['string'],
            'remove_promo_image' => ['sometimes', 'boolean'],
        ]);

        if (filled($validated['store_name'] ?? null)) {
            $profile->update(['store_name' => $validated['store_name']]);
        }

        $draft['branding']['description'] = $validated['description'] ?? ($draft['branding']['description'] ?? '');
        $draft['branding']['slogan'] = $validated['slogan'] ?? ($draft['branding']['slogan'] ?? '');

        if (array_key_exists('social_facebook', $validated)) {
            $draft['branding']['social_facebook'] = $validated['social_facebook'] ?? '';
        }
        if (array_key_exists('social_instagram', $validated)) {
            $draft['branding']['social_instagram'] = $validated['social_instagram'] ?? '';
        }
        if (array_key_exists('website', $validated)) {
            $draft['branding']['website'] = $validated['website'] ?? '';
        }

        if (filled($validated['preset'] ?? null)) {
            $draft['theme']['preset'] = $validated['preset'];
        }

        if (array_key_exists('announcement_enabled', $validated)) {
            $draft['announcement']['enabled'] = (bool) $validated['announcement_enabled'];
            $draft['sections']['enabled']['announcement'] = (bool) $validated['announcement_enabled'];
        }
        if (array_key_exists('announcement_text', $validated)) {
            $draft['announcement']['text'] = $validated['announcement_text'] ?? '';
        }

        if (array_key_exists('promo_enabled', $validated)) {
            $draft['promo_banner']['enabled'] = (bool) $validated['promo_enabled'];
            $draft['sections']['enabled']['promo'] = (bool) $validated['promo_enabled'];
        }
        if (array_key_exists('promo_text', $validated)) {
            $draft['promo_banner']['text'] = $validated['promo_text'] ?? '';
        }

        if (! empty($validated['hero_autoplay_seconds'])) {
            $draft['hero']['autoplay_seconds'] = (int) $validated['hero_autoplay_seconds'];
        }

        foreach ($validated['sections_enabled'] ?? [] as $key => $enabled) {
            if (in_array($key, ['announcement', 'promo', 'featured', 'about', 'contact'], true)) {
                $draft['sections']['enabled'][$key] = (bool) $enabled;
            }
        }

        $removeHero = $validated['remove_hero_paths'] ?? [];
        if ($removeHero !== []) {
            $draft['hero']['images'] = array_values(array_filter(
                $draft['hero']['images'] ?? [],
                fn ($path) => ! in_array($path, $removeHero, true)
            ));
        }

        if ($request->boolean('remove_promo_image')) {
            $draft['promo_banner']['image'] = null;
        }

        $customization = $this->customizations->updateDraft($customization, $draft);
        $this->customizations->syncBrandingToProfile($profile->fresh(), $this->customizations->draftSettings($customization));

        return response()->json(array_merge($this->payload($request), [
            'message' => 'Store details saved as draft.',
        ]));
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'image', 'max:5120']]);

        return $this->uploadBrandingImage($request, 'store_logo', 'logo');
    }

    public function uploadCover(Request $request): JsonResponse
    {
        $request->validate(['cover' => ['required', 'image', 'max:5120']]);

        return $this->uploadBrandingImage($request, 'cover_image', 'cover');
    }

    public function uploadHero(Request $request): JsonResponse
    {
        $request->validate([
            'hero_images' => ['required', 'array', 'min:1', 'max:8'],
            'hero_images.*' => ['image', 'max:5120'],
        ]);

        $profile = $request->user()->sellerProfile;
        $customization = $this->customizations->forProfile($profile);
        $draft = $this->customizations->draftSettings($customization);
        $basePath = "stores/customization/{$profile->id}";
        $heroImages = $draft['hero']['images'] ?? [];

        $files = $request->file('hero_images') ?? [];
        if (! is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            $heroImages[] = $file->store($basePath, 'public');
        }

        $draft['hero']['images'] = array_slice(array_values($heroImages), 0, 8);
        $this->customizations->updateDraft($customization, $draft);

        return response()->json(array_merge($this->payload($request), [
            'message' => 'Slideshow photos added.',
        ]));
    }

    public function uploadPromo(Request $request): JsonResponse
    {
        $request->validate(['promo_image' => ['required', 'image', 'max:5120']]);

        $profile = $request->user()->sellerProfile;
        $customization = $this->customizations->forProfile($profile);
        $draft = $this->customizations->draftSettings($customization);
        $basePath = "stores/customization/{$profile->id}";
        $draft['promo_banner']['image'] = $request->file('promo_image')->store($basePath, 'public');
        $this->customizations->updateDraft($customization, $draft);

        return response()->json(array_merge($this->payload($request), [
            'message' => 'Promo image updated.',
        ]));
    }

    public function publish(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $this->customizations->forProfile($profile);
        $this->customizations->publish($customization);
        $this->customizations->syncBrandingToProfile($profile, $this->customizations->publishedSettings($customization->fresh()));

        return response()->json(array_merge($this->payload($request), [
            'message' => 'Store appearance published.',
        ]));
    }

    public function completeSetup(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $this->customizations->forProfile($profile);
        $this->customizations->completeSetup($customization);

        return response()->json(array_merge($this->payload($request), [
            'message' => 'Store setup complete.',
        ]));
    }

    private function uploadBrandingImage(Request $request, string $brandingKey, string $fileField): JsonResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $this->customizations->forProfile($profile);
        $draft = $this->customizations->draftSettings($customization);
        $basePath = "stores/customization/{$profile->id}";
        $path = $request->file($fileField)->store($basePath, 'public');
        $draft['branding'][$brandingKey] = $path;
        $customization = $this->customizations->updateDraft($customization, $draft);
        $this->customizations->syncBrandingToProfile($profile, $this->customizations->draftSettings($customization));

        return response()->json(array_merge($this->payload($request), [
            'message' => $brandingKey === 'store_logo' ? 'Store logo updated.' : 'Cover image updated.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $profile = $request->user()->sellerProfile;
        $customization = $this->customizations->forProfile($profile);
        $draft = $this->customizations->draftSettings($customization);
        $enabled = $draft['sections']['enabled'] ?? [];

        return [
            'store_name' => $profile->displayName(),
            'slug' => $profile->slug,
            'store_url' => $profile->slug ? url('/store/'.$profile->slug) : null,
            'description' => $draft['branding']['description'] ?? $profile->store_description,
            'slogan' => $draft['branding']['slogan'] ?? '',
            'logo_url' => $this->publicUrl($draft['branding']['store_logo'] ?? $profile->shop_photo),
            'cover_url' => $this->publicUrl($draft['branding']['cover_image'] ?? null),
            'preset' => $draft['theme']['preset'] ?? 'ocean',
            'theme' => $draft['theme'] ?? [],
            'setup_complete' => $customization->isSetupComplete(),
            'published_at' => $customization->published_at?->toIso8601String(),
            'presets' => collect(StoreCustomizationService::presets())->map(fn ($preset, $key) => [
                'key' => $key,
                'label' => $preset['label'],
                'primary_color' => $preset['primary_color'],
                'secondary_color' => $preset['secondary_color'],
            ])->values(),
            'hero_images' => collect($draft['hero']['images'] ?? [])->map(fn ($path) => [
                'path' => $path,
                'url' => $this->publicUrl($path),
            ])->values(),
            'hero_autoplay_seconds' => (int) ($draft['hero']['autoplay_seconds'] ?? 5),
            'announcement' => [
                'enabled' => (bool) ($draft['announcement']['enabled'] ?? false),
                'text' => $draft['announcement']['text'] ?? '',
            ],
            'promo' => [
                'enabled' => (bool) ($draft['promo_banner']['enabled'] ?? false),
                'text' => $draft['promo_banner']['text'] ?? '',
                'image_url' => $this->publicUrl($draft['promo_banner']['image'] ?? null),
                'image_path' => $draft['promo_banner']['image'] ?? null,
            ],
            'social' => [
                'facebook' => $draft['branding']['social_facebook'] ?? '',
                'instagram' => $draft['branding']['social_instagram'] ?? '',
                'website' => $draft['branding']['website'] ?? '',
            ],
            'sections_enabled' => [
                'announcement' => (bool) ($enabled['announcement'] ?? false),
                'promo' => (bool) ($enabled['promo'] ?? false),
                'featured' => (bool) ($enabled['featured'] ?? true),
                'about' => (bool) ($enabled['about'] ?? true),
                'contact' => (bool) ($enabled['contact'] ?? true),
            ],
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : Storage::disk('public')->url($path);
    }
}
