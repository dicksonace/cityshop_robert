<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StoreCustomizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreCustomizationController extends Controller
{
    public function setup(Request $request, StoreCustomizationService $customizations): Response|RedirectResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $customizations->forProfile($profile);

        if ($customization->isSetupComplete()) {
            return redirect()->route('seller.dashboard');
        }

        return Inertia::render('seller/store-setup', [
            'customization' => $customizations->draftSettings($customization),
            'presets' => StoreCustomizationService::presets(),
            'storeUrl' => route('store.show', $profile->slug, absolute: true),
            'storeName' => $profile->displayName(),
        ]);
    }

    public function appearance(Request $request, StoreCustomizationService $customizations): Response
    {
        $profile = $request->user()->sellerProfile;
        $customization = $customizations->forProfile($profile);

        return Inertia::render('seller/store-appearance', [
            'customization' => $customizations->draftSettings($customization),
            'publishedCustomization' => $customizations->publishedSettings($customization),
            'presets' => StoreCustomizationService::presets(),
            'setupComplete' => $customization->isSetupComplete(),
            'storeUrl' => route('store.show', $profile->slug, absolute: true),
            'storeName' => $profile->displayName(),
            'profile' => $profile->load('user'),
            'previewProducts' => Product::query()
                ->with('images')
                ->where('seller_id', $profile->user_id)
                ->visibleInShop()
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function updateDraft(Request $request, StoreCustomizationService $customizations): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $customizations->forProfile($profile);

        $validated = $request->validate([
            'settings' => ['required', 'string'],
            'store_logo' => ['nullable', 'image', 'max:5120'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'hero_images' => ['nullable', 'array', 'max:8'],
            'hero_images.*' => ['image', 'max:5120'],
            'promo_image' => ['nullable', 'image', 'max:5120'],
            'remove_store_logo' => ['boolean'],
            'remove_cover_image' => ['boolean'],
            'remove_promo_image' => ['boolean'],
            'remove_hero_images' => ['nullable', 'array'],
            'remove_hero_images.*' => ['string'],
        ]);

        $incoming = json_decode($validated['settings'], true, 512, JSON_THROW_ON_ERROR);
        $current = $customizations->draftSettings($customization);
        $basePath = "stores/customization/{$profile->id}";

        if ($request->boolean('remove_store_logo')) {
            $incoming['branding']['store_logo'] = null;
        } elseif ($request->hasFile('store_logo')) {
            $incoming['branding']['store_logo'] = $request->file('store_logo')->store($basePath, 'public');
        } else {
            $incoming['branding']['store_logo'] = $current['branding']['store_logo'] ?? null;
        }

        if ($request->boolean('remove_cover_image')) {
            $incoming['branding']['cover_image'] = null;
        } elseif ($request->hasFile('cover_image')) {
            $incoming['branding']['cover_image'] = $request->file('cover_image')->store($basePath, 'public');
        } else {
            $incoming['branding']['cover_image'] = $current['branding']['cover_image'] ?? null;
        }

        if ($request->boolean('remove_promo_image')) {
            $incoming['promo_banner']['image'] = null;
        } elseif ($request->hasFile('promo_image')) {
            $incoming['promo_banner']['image'] = $request->file('promo_image')->store($basePath, 'public');
        } else {
            $incoming['promo_banner']['image'] = $current['promo_banner']['image'] ?? null;
        }

        $heroImages = $current['hero']['images'] ?? [];
        $removeHero = $validated['remove_hero_images'] ?? [];
        $heroImages = array_values(array_filter($heroImages, fn ($path) => ! in_array($path, $removeHero, true)));

        if ($request->hasFile('hero_images')) {
            foreach ($request->file('hero_images') as $file) {
                $heroImages[] = $file->store($basePath, 'public');
            }
        }

        $incoming['hero']['images'] = array_slice($heroImages, 0, 8);

        $customization = $customizations->updateDraft($customization, $incoming);
        $customizations->syncBrandingToProfile($profile, $customizations->draftSettings($customization));

        return back()->with('success', 'Draft saved.');
    }

    public function publish(Request $request, StoreCustomizationService $customizations): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $customizations->forProfile($profile);

        $customizations->publish($customization);
        $customizations->syncBrandingToProfile($profile, $customizations->publishedSettings($customization->fresh()));

        return back()->with('success', 'Store published successfully.');
    }

    public function reset(Request $request, StoreCustomizationService $customizations): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $customizations->forProfile($profile);

        $customizations->reset($customization);

        return back()->with('success', 'Store theme reset to defaults.');
    }

    public function completeSetup(Request $request, StoreCustomizationService $customizations): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;
        $customization = $customizations->forProfile($profile);

        $customizations->completeSetup($customization);
        $customizations->syncBrandingToProfile($profile, $customizations->publishedSettings($customization->fresh()));

        return redirect()->route('seller.dashboard')->with('success', 'Your store is ready! Start adding products.');
    }
}
