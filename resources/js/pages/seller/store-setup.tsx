import { Head } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, LoaderCircle, Palette, ImageIcon, Store } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { completeStoreSetup, submitStoreDraft } from '@/lib/store-customization-form';
import { productImageUrl } from '@/types/marketplace';
import { StoreCustomizationSettings, ThemePreset } from '@/types/store-customization';

interface StoreSetupProps {
    customization: StoreCustomizationSettings;
    presets: ThemePreset;
    storeUrl: string;
    storeName: string;
}

const steps = [
    { id: 1, title: 'Branding', icon: Store },
    { id: 2, title: 'Theme', icon: Palette },
    { id: 3, title: 'Hero', icon: ImageIcon },
    { id: 4, title: 'Launch', icon: Check },
];

export default function StoreSetup({ customization: initial, presets, storeUrl, storeName }: StoreSetupProps) {
    const [step, setStep] = useState(1);
    const [settings, setSettings] = useState<StoreCustomizationSettings>(initial);
    const [saving, setSaving] = useState(false);
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [coverFile, setCoverFile] = useState<File | null>(null);
    const [heroFiles, setHeroFiles] = useState<File[]>([]);

    const saveAndNext = () => {
        setSaving(true);
        submitStoreDraft(settings, { store_logo: logoFile, cover_image: coverFile, hero_images: heroFiles }, {}, {
            onSuccess: () => {
                setSaving(false);
                setLogoFile(null);
                setCoverFile(null);
                setHeroFiles([]);
                setStep((s) => Math.min(s + 1, 4));
            },
        });
    };

    const finish = (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        submitStoreDraft(settings, { store_logo: logoFile, cover_image: coverFile, hero_images: heroFiles }, {}, {
            onSuccess: () => {
                completeStoreSetup();
            },
        });
    };

    const applyPreset = (key: string) => {
        const preset = presets[key];
        if (!preset) return;
        setSettings((prev) => ({
            ...prev,
            theme: {
                ...prev.theme,
                preset: key,
                primary_color: preset.primary_color,
                secondary_color: preset.secondary_color,
                background_color: preset.background_color,
                text_color: preset.text_color,
            },
        }));
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-orange-50 to-white">
            <Head title="Set Up Your Store" />
            <div className="mx-auto max-w-3xl px-4 py-10">
                <div className="text-center">
                    <h1 className="text-2xl font-bold text-gray-900">Welcome to CityShop, {storeName}!</h1>
                    <p className="mt-2 text-gray-500">Customize your storefront before you start selling.</p>
                </div>

                <div className="mt-8 flex justify-center gap-2">
                    {steps.map((s) => (
                        <div
                            key={s.id}
                            className={`flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ${
                                step === s.id ? 'bg-orange-500 text-white' : step > s.id ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                            }`}
                        >
                            <s.icon className="h-3.5 w-3.5" />
                            {s.title}
                        </div>
                    ))}
                </div>

                <form onSubmit={step === 4 ? finish : (e) => { e.preventDefault(); saveAndNext(); }} className="mt-8 rounded-2xl bg-white p-6 shadow-sm">
                    {step === 1 && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold">Store branding</h2>
                            <div>
                                <Label>Slogan</Label>
                                <Input value={settings.branding.slogan} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, slogan: e.target.value } })} className="mt-1" placeholder="Quality products, fast delivery" />
                            </div>
                            <div>
                                <Label>Store description</Label>
                                <textarea
                                    value={settings.branding.description}
                                    onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, description: e.target.value } })}
                                    className="mt-1 w-full rounded-md border border-gray-200 px-3 py-2 text-sm"
                                    rows={4}
                                    placeholder="Tell buyers about your store..."
                                />
                            </div>
                            <div>
                                <Label>Business category</Label>
                                <Input value={settings.branding.business_category} onChange={(e) => setSettings({ ...settings, branding: { ...settings.branding, business_category: e.target.value } })} className="mt-1" placeholder="Electronics, Fashion, etc." />
                            </div>
                            <div>
                                <Label>Store logo</Label>
                                <Input type="file" accept="image/*" className="mt-1" onChange={(e) => setLogoFile(e.target.files?.[0] ?? null)} />
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold">Choose a theme</h2>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {Object.entries(presets).map(([key, preset]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => applyPreset(key)}
                                        className={`rounded-xl border-2 p-3 text-left transition-all ${settings.theme.preset === key ? 'border-orange-500 ring-2 ring-orange-200' : 'border-gray-100 hover:border-gray-200'}`}
                                    >
                                        <div className="flex h-10 gap-1 overflow-hidden rounded-lg">
                                            <div className="flex-1" style={{ backgroundColor: preset.primary_color }} />
                                            <div className="flex-1" style={{ backgroundColor: preset.secondary_color }} />
                                        </div>
                                        <p className="mt-2 text-sm font-medium">{preset.label}</p>
                                    </button>
                                ))}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label>Primary color</Label>
                                    <Input type="color" value={settings.theme.primary_color} onChange={(e) => setSettings({ ...settings, theme: { ...settings.theme, primary_color: e.target.value } })} className="mt-1 h-10" />
                                </div>
                                <div>
                                    <Label>Accent color</Label>
                                    <Input type="color" value={settings.theme.secondary_color} onChange={(e) => setSettings({ ...settings, theme: { ...settings.theme, secondary_color: e.target.value } })} className="mt-1 h-10" />
                                </div>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold">Hero section</h2>
                            <div className="grid grid-cols-3 gap-2">
                                {(['static', 'slideshow', 'minimal'] as const).map((type) => (
                                    <button
                                        key={type}
                                        type="button"
                                        onClick={() => setSettings({ ...settings, hero: { ...settings.hero, type } })}
                                        className={`rounded-xl border-2 px-3 py-4 text-sm font-medium capitalize ${settings.hero.type === type ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-100'}`}
                                    >
                                        {type}
                                    </button>
                                ))}
                            </div>
                            <div>
                                <Label>Cover / banner image</Label>
                                <Input type="file" accept="image/*" className="mt-1" onChange={(e) => setCoverFile(e.target.files?.[0] ?? null)} />
                            </div>
                            {settings.hero.type === 'slideshow' && (
                                <>
                                    <div>
                                        <Label>Slideshow images (up to 8)</Label>
                                        <Input type="file" accept="image/*" multiple className="mt-1" onChange={(e) => setHeroFiles(Array.from(e.target.files ?? []))} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <Label>Autoplay (seconds)</Label>
                                            <Input type="number" min={2} max={30} value={settings.hero.autoplay_seconds} onChange={(e) => setSettings({ ...settings, hero: { ...settings.hero, autoplay_seconds: Number(e.target.value) } })} className="mt-1" />
                                        </div>
                                        <div className="flex flex-col justify-end gap-2 text-sm">
                                            <label className="flex items-center gap-2">
                                                <input type="checkbox" checked={settings.hero.show_arrows} onChange={(e) => setSettings({ ...settings, hero: { ...settings.hero, show_arrows: e.target.checked } })} />
                                                Show arrows
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <input type="checkbox" checked={settings.hero.show_indicators} onChange={(e) => setSettings({ ...settings, hero: { ...settings.hero, show_indicators: e.target.checked } })} />
                                                Show indicators
                                            </label>
                                        </div>
                                    </div>
                                    {settings.hero.images.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {settings.hero.images.map((img) => (
                                                <img key={img} src={productImageUrl(img)} alt="" className="h-16 w-16 rounded-lg object-cover" />
                                            ))}
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    )}

                    {step === 4 && (
                        <div className="space-y-4 text-center">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                                <Check className="h-8 w-8 text-green-600" />
                            </div>
                            <h2 className="text-lg font-semibold">Ready to launch!</h2>
                            <p className="text-sm text-gray-500">
                                Your store will be published at:<br />
                                <a href={storeUrl} target="_blank" rel="noreferrer" className="text-orange-500 hover:underline">{storeUrl}</a>
                            </p>
                            <p className="text-xs text-gray-400">You can customize further anytime from Customize Store in your dashboard.</p>
                        </div>
                    )}

                    <div className="mt-8 flex justify-between">
                        <Button type="button" variant="outline" disabled={step === 1 || saving} onClick={() => setStep((s) => s - 1)}>
                            <ArrowLeft className="mr-1 h-4 w-4" /> Back
                        </Button>
                        <Button type="submit" disabled={saving} className="bg-orange-500 hover:bg-orange-600">
                            {saving && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            {step === 4 ? 'Publish & Start Selling' : 'Save & Continue'}
                            {step < 4 && <ArrowRight className="ml-1 h-4 w-4" />}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
