import { Link } from '@inertiajs/react';
import { ArrowRight, ShieldCheck, Sparkles, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Slide {
    title: string;
    subtitle: string;
    accent: string;
}

interface HeroBannerProps {
    slides: Slide[];
}

export default function HeroBanner({ slides }: HeroBannerProps) {
    const [current, setCurrent] = useState(0);

    useEffect(() => {
        const timer = setInterval(() => setCurrent((prev) => (prev + 1) % slides.length), 6000);
        return () => clearInterval(timer);
    }, [slides.length]);

    const slide = slides[current];

    return (
        <section className="relative overflow-hidden">
            <div className={`bg-gradient-to-br ${slide.accent} transition-all duration-700`}>
                <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggIGQ9Ik0zNiAxOGMzLjMxNCAwIDYgMi42ODYgNiA2cy0yLjY4NiA2LTYgNi02LTIuNjg2LTYtNiAyLjY4Ni02IDYtNiIvPjwvZz48L2c+PC9zdmc+')] opacity-60" />
                <div className="relative mx-auto max-w-7xl px-3 py-8 sm:px-4 sm:py-14 md:py-20">
                    <div className="grid items-center gap-6 md:grid-cols-2 md:gap-8">
                        <div className="text-white">
                            <div className="mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur-sm sm:mb-4">
                                <Sparkles className="h-3.5 w-3.5" />
                                Ghana&apos;s Trusted Marketplace
                            </div>
                            <h1 className="text-2xl font-extrabold leading-tight tracking-tight transition-all sm:text-3xl md:text-5xl">{slide.title}</h1>
                            <p className="mt-3 max-w-lg text-sm text-white/85 sm:mt-4 sm:text-base md:text-lg">{slide.subtitle}</p>
                            <div className="mt-6 flex flex-wrap gap-2 sm:mt-8 sm:gap-3">
                                <Link
                                    href={route('home')}
                                    className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2.5 text-sm font-bold text-gray-900 shadow-lg transition-transform hover:scale-105 sm:px-6 sm:py-3"
                                >
                                    Shop Now <ArrowRight className="h-4 w-4" />
                                </Link>
                                <Link
                                    href={route('register.seller')}
                                    className="inline-flex items-center gap-2 rounded-full border-2 border-white/40 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition-colors hover:bg-white/10 sm:px-6 sm:py-3"
                                >
                                    Become a Seller
                                </Link>
                            </div>
                        </div>

                        <div className="hidden md:block">
                            <div className="grid grid-cols-2 gap-3">
                                {[
                                    { icon: ShieldCheck, label: 'Verified Sellers', sub: 'ID & business checked' },
                                    { icon: Truck, label: 'Fast Delivery', sub: 'Sellers deliver nationwide' },
                                    { icon: Sparkles, label: 'Best Prices', sub: 'Compare & save' },
                                    { icon: ShieldCheck, label: 'Buyer Protection', sub: 'Dispute resolution' },
                                ].map((item) => (
                                    <div key={item.label} className="rounded-2xl bg-white/10 p-4 backdrop-blur-sm">
                                        <item.icon className="h-6 w-6 text-white/90" />
                                        <p className="mt-2 text-sm font-bold text-white">{item.label}</p>
                                        <p className="text-xs text-white/70">{item.sub}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Mobile feature chips */}
                    <div className="mt-6 flex gap-2 overflow-x-auto pb-1 md:hidden">
                        {[
                            { icon: ShieldCheck, label: 'Verified Sellers' },
                            { icon: Truck, label: 'Fast Delivery' },
                            { icon: Sparkles, label: 'Best Prices' },
                            { icon: ShieldCheck, label: 'Buyer Protection' },
                        ].map((item) => (
                            <div key={item.label} className="flex shrink-0 items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-medium text-white backdrop-blur-sm">
                                <item.icon className="h-3.5 w-3.5" />
                                {item.label}
                            </div>
                        ))}
                    </div>

                    <div className="mt-6 flex justify-center gap-2 sm:mt-8 md:justify-start">
                        {slides.map((_, i) => (
                            <button
                                key={i}
                                type="button"
                                onClick={() => setCurrent(i)}
                                className={`h-1.5 rounded-full transition-all ${i === current ? 'w-8 bg-white' : 'w-3 bg-white/40'}`}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
