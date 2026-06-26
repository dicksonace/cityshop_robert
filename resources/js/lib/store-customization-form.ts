import { router } from '@inertiajs/react';

import { StoreCustomizationSettings } from '@/types/store-customization';

interface StoreDraftFiles {
    store_logo?: File | null;
    cover_image?: File | null;
    hero_images?: File[];
    promo_image?: File | null;
}

interface StoreDraftRemoves {
    remove_store_logo?: boolean;
    remove_cover_image?: boolean;
    remove_promo_image?: boolean;
    remove_hero_images?: string[];
}

export function submitStoreDraft(
    settings: StoreCustomizationSettings,
    files: StoreDraftFiles = {},
    removes: StoreDraftRemoves = {},
    options?: { onSuccess?: () => void },
) {
    const formData = new FormData();
    formData.append('settings', JSON.stringify(settings));

    if (files.store_logo) formData.append('store_logo', files.store_logo);
    if (files.cover_image) formData.append('cover_image', files.cover_image);
    if (files.promo_image) formData.append('promo_image', files.promo_image);
    files.hero_images?.forEach((file) => formData.append('hero_images[]', file));

    if (removes.remove_store_logo) formData.append('remove_store_logo', '1');
    if (removes.remove_cover_image) formData.append('remove_cover_image', '1');
    if (removes.remove_promo_image) formData.append('remove_promo_image', '1');
    removes.remove_hero_images?.forEach((path) => formData.append('remove_hero_images[]', path));

    router.post(route('seller.store-appearance.draft'), formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: options?.onSuccess,
    });
}

export function publishStore() {
    router.post(route('seller.store-appearance.publish'), {}, { preserveScroll: true });
}

export function resetStore() {
    router.post(route('seller.store-appearance.reset'), {});
}

export function completeStoreSetup() {
    router.post(route('seller.store-appearance.complete-setup'));
}
