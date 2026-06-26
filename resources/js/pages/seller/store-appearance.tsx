import { Head, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, LoaderCircle, Monitor, Smartphone, Tablet } from 'lucide-react';
import { useState } from 'react';

import StoreStorefront from '@/components/store/store-storefront';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PanelLayout from '@/layouts/panel-layout';
import { sellerNav } from '@/lib/seller-nav';
import { publishStore, resetStore, submitStoreDraft } from '@/lib/store-customization-form';
import { Paginated, Product, SellerProfile } from '@/types/marketplace';
import { SECTION_LABELS, StoreCustomizationSettings, ThemePreset } from '@/types/store-customization';
import { productImageUrl } from '@/types/marketplace';
import { SharedData } from '@/types';

interface StoreAppearanceProps {
    customization: StoreCustomizationSettings;
    presets: ThemePreset;
    setupComplete: boolean;
    storeUrl: string;
    storeName: string;
    profile: SellerProfile & { user_id: number; user?: { name: string; city?: string; region?: string; email?: string; mobile?: string; whatsapp?: string } };
    previewProducts: Product[];
}

type Tab = 'theme' | 'hero' | 'branding' | 'layout' | 'announcement' | 'promo';
type PreviewDevice = 'mobile' | 'tablet' | 'desktop';

const emptyPagination = (items: Product[]): Paginated<Product> => ({
    data: items,
    current_page: 1,
    last_page: 1,
    per_page: items.length || 12,
    total: items.length,
    links: [],
});

export default function StoreAppearance({
    customization: initial,
    presets,
    setupComplete,
    storeUrl,
    storeName,
    profile,
    previewProducts,
}: StoreAppearanceProps) {
    const { flash } = usePage<SharedData>().props;
    const [tab, setTab] = useState<Tab>('theme');
    const [settings, setSettings] = useState<StoreCustomizationSettings>(initial);
    const [device, setDevice] = useState<PreviewDevice>('desktop');
    const [saving, setSaving] = useState(false);
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [coverFile, setCoverFile] = useState<File | null>(null);
    const [heroFiles, setHeroFiles] = useState<File[]>([]);
    const [promoFile, setPromoFile] = useState<File | null>(null);
    const [removedHeroImages, setRemovedHeroImages] = useState<string[]>([]);

    const previewWidth = device === 'mobile' ? '375px' : device === 'tablet' ? '768px' : '100%';

    const saveDraft = () => {
        setSaving(true);
        submitStoreDraft(settings, { store_logo: logoFile, cover_image: coverFile, hero_images: heroFiles, promo_image: promoFile }, { remove_hero_images: removedHeroImages }, {
            onSuccess: () => {
                setSaving(false);
                setLogoFile(null);
                setCoverFile(null);
                setHeroFiles([]);
                setPromoFile(null);
                setRemovedHeroImages([]);
            },
        });
    };

    const applyPreset = (key: string) => {
        const preset = presets[key];
        if (!preset) return;
        setSettings((prev) => ({
            ...prev,
            theme: { ...prev.theme, preset: key, primary_color: preset.primary_color, secondary_color: preset.secondary_color, background_color: preset.background_color, text_color: preset.text_color },
        }));
    };

    const moveSection = (index: number, direction: -1 | 1) => {
        const order = [...settings.sections.order];
        const target = index + direction;
        if (target < 0 || target >= order.length) return;
        [order[index], order[target]] = [order[target], order[index]];
        setSettings({ ...settings, sections: { ...settings.sections, order } });
    };

    const previewStore = {
        ...profile,
        user_id: profile.user_id!,
        store_description: settings.branding.description || profile.store_description,
    };

    const tabs: { id: Tab; label: string }[] = [
        { id: 'theme', label: 'Theme' },
        { id: 'hero', label: 'Hero' },
        { id: 'branding', label: 'Branding' },
        { id: 'layout', label: 'Layout' },
        { id: 'announcement', label: 'Announcement' },
        { id: 'promo', label: 'Promo' },
    ];

    return (
        <PanelLayout title="Customize Store" nav={sellerNav('appearance')}>
            <Head title="Customize Store" />

            {flash.success && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{flash.success}</div>
            )}

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-500">
                    {setupComplete ? 'Changes save as draft until you publish.' : 'Complete setup to unlock your dashboard.'}
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" onClick={() => resetStore()}>Reset defaults</Button>
                    <Button variant="outline" size="sm" disabled={saving} onClick={saveDraft}>
                        {saving && <LoaderCircle className="mr-1 h-3.5 w-3.5 animate-spin" />}
                        Save draft
                    </Button>
                    <Button size="sm" className="bg-orange-500 hover:bg-orange-600" onClick={() => publishStore()}>
                        Publish
                    </Button>
                    <a href={storeUrl} target="_blank" rel="noreferrer" className="text-sm text-orange-500 hover:underline">View live store</a>
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <div className="rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div className="flex flex-wrap gap-1 border-b border-gray-100 p-2">
                        {tabs.map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => setTab(t.id)}
                                className={`rounded-lg px-3 py-1.5 text-sm font-medium ${tab === t.id ? 'bg-orange-50 text-orange-600' : 'text-gray-600 hover:bg-gray-50'}`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>

                    <div className="max-h-[70vh] overflow-y-auto p-4">
                        {tab === 'theme' && (
                            <div className="space-y-4">
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {Object.entries(presets).map(([key, preset]) => (
                                        <button key={key} type="button" onClick={() => applyPreset(key)} className={`rounded-xl border-2 p-2 text-left ${settings.theme.preset === key ? 'border-orange-500' : 'border-gray-100'}`}>
                                            <div className="flex h-8 gap-1 overflow-hidden rounded">
                                                <div className="flex-1" style={{ backgroundColor: preset.primary_color }} />
                                                <div className="flex-1" style={{ backgroundColor: preset.secondary_color }} />
                                            </div>
                                            <p className="mt-1 text-xs font-medium">{preset.label}</p>
                                        </button>
                                    ))}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    {(['primary_color', 'secondary_color', 'background_color', 'text_color'] as const).map((field) => (
                                        <div key={field}>
                                            <Label className="capitalize">{field.replace('_', ' ')}</Label>
                                            <Input type="color" value={settings.theme[field]} onChange={(e) => setSettings({ ...settings, theme: { ...settings.theme, [field]: e.target.value } })} className="mt-1 h-9" />
                                        </div>
                                    ))}
                                </div>
                                <div>
                                    <Label>Button style</Label>
                                    <select value={settings.theme.button_style} onChange={(e) => setSettings({ ...settings, theme: { ...settings.theme, button_style: e.target.value as StoreCustomizationSettings['theme']['button_style'] } })} className="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                        <option value="rounded">Rounded</option>
                                        <option value="square">Square</option>
                                        <option value="pill">Pill</option>
                                    </select>
                                </div>
                            </div>
                        )}

                        {tab === 'hero' && (
                            <div className="space-y-4">
                                <div className="grid grid-cols-3 gap-2">
                                    {(['static', 'slideshow', 'minimal'] as const).map((type) => (
                                        <button key={type} type="button" onClick={() => setSettings({ ...settings, hero: { ...settings.hero, type } })} className={`rounded-lg border px-2 py-3 text-sm capitalize ${settings.hero.type === type ? 'border-orange-500 bg-orange-50' : 'border-gray-100'}`}>{type}</button>
                                    ))}
                                </div>
                                <div>
                                    <Label>Cover image</Label>
                                    <Input type="file" accept="image/*" className="mt-1" onChange={(e) => setCoverFile(e.target.files?.[0] ?? null)} />
                                </div>
                                {settings.hero.type === 'slideshow' && (
                                    <>
                                        <div>
                                            <Label>Add slideshow images</Label>
                                            <Input type="file" accept="image/*" multiple className="mt-1" onChange={(e) => setHeroFiles(Array.from(e.target.files ?? []))} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div>
                                                <Label>Autoplay (sec)</Label>
                                                <Input type="number" min={2} max={30} value={settings.hero.autoplay_seconds} onChange={(e) => setSettings({ ...settings, hero: { ...settings.hero, autoplay_seconds: Number(e.target.value) } })} className="mt-1" />
                                            </div>
                                            <div className="space-y-2 text-sm">
                                                <label className="flex items-center gap-2"><input type="checkbox" checked={settings.hero.show_arrows} onChange={(e) => setSettings({ ...settings, hero: { ...settings.hero, show_arrows: e.target.checked } })} /> Arrows</label>
                                                <label className="flex items-center gap-2"><input type="checkbox" checked={settings.hero.show_indicators} onChange={(e) => setSettings({ ...settings, hero: { ...settings.hero, show_indicators: e.target.checked } })} /> Indicators</label>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {settings.hero.images.map((img) => (
                                                <div key={img} className="relative">
                                                    <img src={productImageUrl(img)} alt="" className="h-14 w-14 rounded object-cover" />
                                                    <button type="button" className="absolute -top-1 -right-1 rounded-full bg-red-500 px-1 text-[10px] text-white" onClick={() => { setRemovedHeroImages((r) => [...r, img]); setSettings({ ...settings, hero: { ...settings.hero, images: settings.hero.images.filter((i) => i !== img) } }); }}>×</button>
                                                </div>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>
                        )}

                        {tab === 'branding' && (
                            <div className="space-y-4">
                                <div><Label>Slogan</Label><Input value={settings.branding.slogan} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, slogan: e.target.value } })} className="mt-1" /></div>
                                <div><Label>Description</Label><textarea value={settings.branding.description} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, description: e.target.value } })} className="mt-1 w-full rounded-md border px-3 py-2 text-sm" rows={3} /></div>
                                <div><Label>Category</Label><Input value={settings.branding.business_category} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, business_category: e.target.value } })} className="mt-1" /></div>
                                <div><Label>Website</Label><Input value={settings.branding.website} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, website: e.target.value } })} className="mt-1" placeholder="https://" /></div>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div><Label>Facebook</Label><Input value={settings.branding.social_facebook} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, social_facebook: e.target.value } })} className="mt-1" /></div>
                                    <div><Label>Instagram</Label><Input value={settings.branding.social_instagram} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, social_instagram: e.target.value } })} className="mt-1" /></div>
                                    <div><Label>Twitter/X</Label><Input value={settings.branding.social_twitter} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, social_twitter: e.target.value } })} className="mt-1" /></div>
                                </div>
                                <div><Label>Store logo</Label><Input type="file" accept="image/*" className="mt-1" onChange={(e) => setLogoFile(e.target.files?.[0] ?? null)} /></div>
                            </div>
                        )}

                        {tab === 'layout' && (
                            <div className="space-y-4">
                                <div>
                                    <Label className="mb-2 block">Section order</Label>
                                    {settings.sections.order.map((section, index) => (
                                        <div key={section} className="mb-2 flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm">
                                            <span className="flex-1">{SECTION_LABELS[section] ?? section}</span>
                                            {section !== 'hero' && section !== 'products' && (
                                                <input type="checkbox" checked={settings.sections.enabled[section] ?? false} onChange={(e) => setSettings({ ...settings, sections: { ...settings.sections, enabled: { ...settings.sections.enabled, [section]: e.target.checked } } })} />
                                            )}
                                            <button type="button" onClick={() => moveSection(index, -1)} className="text-gray-400 hover:text-gray-600"><ChevronUp className="h-4 w-4" /></button>
                                            <button type="button" onClick={() => moveSection(index, 1)} className="text-gray-400 hover:text-gray-600"><ChevronDown className="h-4 w-4" /></button>
                                        </div>
                                    ))}
                                </div>
                                <div className="grid grid-cols-3 gap-3">
                                    <div><Label>Mobile cols</Label><Input type="number" min={1} max={2} value={settings.product_display.columns_mobile} onChange={(e) => setSettings({ ...settings, product_display: { ...settings.product_display, columns_mobile: Number(e.target.value) } })} className="mt-1" /></div>
                                    <div><Label>Tablet cols</Label><Input type="number" min={2} max={4} value={settings.product_display.columns_tablet} onChange={(e) => setSettings({ ...settings, product_display: { ...settings.product_display, columns_tablet: Number(e.target.value) } })} className="mt-1" /></div>
                                    <div><Label>Desktop cols</Label><Input type="number" min={3} max={5} value={settings.product_display.columns_desktop} onChange={(e) => setSettings({ ...settings, product_display: { ...settings.product_display, columns_desktop: Number(e.target.value) } })} className="mt-1" /></div>
                                </div>
                                <div>
                                    <Label>Product layout</Label>
                                    <select value={settings.product_display.layout} onChange={(e) => setSettings({ ...settings, product_display: { ...settings.product_display, layout: e.target.value as 'grid' | 'list' } })} className="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                        <option value="grid">Grid</option>
                                        <option value="list">List</option>
                                    </select>
                                </div>
                            </div>
                        )}

                        {tab === 'announcement' && (
                            <div className="space-y-4">
                                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={settings.announcement.enabled} onChange={(e) => setSettings({ ...settings, announcement: { ...settings.announcement, enabled: e.target.checked } })} /> Enable announcement bar</label>
                                <div><Label>Message</Label><Input value={settings.announcement.text} onChange={(e) => setSettings({ ...settings, announcement: { ...settings.announcement, text: e.target.value } })} className="mt-1" placeholder="Free delivery on orders above GHS 200" /></div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div><Label>Background</Label><Input type="color" value={settings.announcement.background_color} onChange={(e) => setSettings({ ...settings, announcement: { ...settings.announcement, background_color: e.target.value } })} className="mt-1 h-9" /></div>
                                    <div><Label>Text color</Label><Input type="color" value={settings.announcement.text_color} onChange={(e) => setSettings({ ...settings, announcement: { ...settings.announcement, text_color: e.target.value } })} className="mt-1 h-9" /></div>
                                </div>
                            </div>
                        )}

                        {tab === 'promo' && (
                            <div className="space-y-4">
                                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={settings.promo_banner.enabled} onChange={(e) => setSettings({ ...settings, promo_banner: { ...settings.promo_banner, enabled: e.target.checked } })} /> Enable promo banner</label>
                                <div><Label>Promo text</Label><Input value={settings.promo_banner.text} onChange={(e) => setSettings({ ...settings, promo_banner: { ...settings.promo_banner, text: e.target.value } })} className="mt-1" placeholder="20% Off This Weekend" /></div>
                                <div><Label>Promo image</Label><Input type="file" accept="image/*" className="mt-1" onChange={(e) => setPromoFile(e.target.files?.[0] ?? null)} /></div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div><Label>Start date</Label><Input type="datetime-local" value={settings.promo_banner.starts_at?.slice(0, 16) ?? ''} onChange={(e) => setSettings({ ...settings, promo_banner: { ...settings.promo_banner, starts_at: e.target.value ? new Date(e.target.value).toISOString() : null } })} className="mt-1" /></div>
                                    <div><Label>End date</Label><Input type="datetime-local" value={settings.promo_banner.ends_at?.slice(0, 16) ?? ''} onChange={(e) => setSettings({ ...settings, promo_banner: { ...settings.promo_banner, ends_at: e.target.value ? new Date(e.target.value).toISOString() : null } })} className="mt-1" /></div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 bg-gray-100 p-4">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-gray-700">Live preview</h3>
                        <div className="flex gap-1">
                            <button type="button" onClick={() => setDevice('mobile')} className={`rounded p-1.5 ${device === 'mobile' ? 'bg-white shadow' : ''}`}><Smartphone className="h-4 w-4" /></button>
                            <button type="button" onClick={() => setDevice('tablet')} className={`rounded p-1.5 ${device === 'tablet' ? 'bg-white shadow' : ''}`}><Tablet className="h-4 w-4" /></button>
                            <button type="button" onClick={() => setDevice('desktop')} className={`rounded p-1.5 ${device === 'desktop' ? 'bg-white shadow' : ''}`}><Monitor className="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div className="mx-auto overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg" style={{ width: previewWidth, maxWidth: '100%' }}>
                        <div className="max-h-[75vh] overflow-y-auto">
                            <StoreStorefront
                                store={previewStore}
                                customization={settings}
                                sections={settings.sections.order.filter((s) => s === 'hero' || s === 'products' || settings.sections.enabled[s])}
                                products={emptyPagination(previewProducts)}
                                featuredProducts={previewProducts.slice(0, 4)}
                                productCount={previewProducts.length}
                                storeUrl={storeUrl}
                                sellerReviewCount={0}
                                promoActive={settings.promo_banner.enabled}
                                previewMode
                            />
                        </div>
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}
