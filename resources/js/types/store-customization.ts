export interface StoreThemeSettings {
    preset: string;
    primary_color: string;
    secondary_color: string;
    background_color: string;
    text_color: string;
    button_style: 'rounded' | 'square' | 'pill';
    font_family: string;
}

export interface StoreHeroSettings {
    type: 'static' | 'slideshow' | 'minimal';
    images: string[];
    autoplay_seconds: number;
    show_arrows: boolean;
    show_indicators: boolean;
}

export interface StoreBrandingSettings {
    store_logo: string | null;
    cover_image: string | null;
    slogan: string;
    description: string;
    business_category: string;
    social_facebook: string;
    social_instagram: string;
    social_twitter: string;
    website: string;
}

export interface StoreSectionsSettings {
    order: string[];
    enabled: Record<string, boolean>;
}

export interface StoreProductDisplaySettings {
    columns_mobile: number;
    columns_tablet: number;
    columns_desktop: number;
    layout: 'grid' | 'list';
    card_style: 'shadow' | 'border' | 'flat';
    border_radius: 'small' | 'medium' | 'large';
    image_aspect: 'square' | 'portrait' | 'landscape';
}

export interface StoreAnnouncementSettings {
    enabled: boolean;
    text: string;
    background_color: string;
    text_color: string;
}

export interface StorePromoBannerSettings {
    enabled: boolean;
    text: string;
    background_color: string;
    text_color: string;
    image: string | null;
    starts_at: string | null;
    ends_at: string | null;
}

export interface StoreCustomizationSettings {
    theme: StoreThemeSettings;
    hero: StoreHeroSettings;
    branding: StoreBrandingSettings;
    sections: StoreSectionsSettings;
    product_display: StoreProductDisplaySettings;
    announcement: StoreAnnouncementSettings;
    promo_banner: StorePromoBannerSettings;
}

export type ThemePreset = Record<string, { label: string; primary_color: string; secondary_color: string; background_color: string; text_color: string }>;

export const SECTION_LABELS: Record<string, string> = {
    announcement: 'Announcement Bar',
    hero: 'Hero Banner',
    promo: 'Promo Banner',
    featured: 'Featured Products',
    products: 'All Products',
    about: 'About Store',
    contact: 'Contact',
};

export function productGridClass(display: StoreProductDisplaySettings): string {
    const mobile = display.columns_mobile === 1 ? 'grid-cols-1' : 'grid-cols-2';
    const tablet = display.columns_tablet === 2 ? 'sm:grid-cols-2' : display.columns_tablet === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-4';
    const desktop = display.columns_desktop === 3 ? 'lg:grid-cols-3' : display.columns_desktop === 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-5';

    return `grid ${mobile} gap-2 sm:gap-4 ${tablet} ${desktop}`;
}

export function cardRadiusClass(radius: StoreProductDisplaySettings['border_radius']): string {
    if (radius === 'small') return 'rounded-lg';
    if (radius === 'large') return 'rounded-2xl';

    return 'rounded-xl';
}

export function buttonRadiusClass(style: StoreThemeSettings['button_style']): string {
    if (style === 'square') return 'rounded-md';
    if (style === 'pill') return 'rounded-full';

    return 'rounded-xl';
}
