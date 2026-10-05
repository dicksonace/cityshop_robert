import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

const slides = [
    '/slider/hero-2.jpg',
    '/slider/hero-4.jpg',
    '/slider/hero-6.jpg',
    '/slider/rail-1.png',
    '/slider/tile-2.png',
    '/slider/tile-3.png',
    '/slider/tile-4.png',
    '/slider/tile-6.png',
];

export default function HeroBanner() {
    const [current, setCurrent] = useState(0);

    useEffect(() => {
        const timer = window.setInterval(() => {
            setCurrent((prev) => (prev + 1) % slides.length);
        }, 4500);
        return () => window.clearInterval(timer);
    }, []);

    const go = (index: number) => {
        setCurrent((index + slides.length) % slides.length);
    };

    return (
        <section className="mx-auto max-w-7xl px-3 pt-3 sm:px-4 sm:pt-4">
            <div className="group relative overflow-hidden rounded-2xl bg-slate-950 shadow-lg">
                <div
                    className="flex transition-transform duration-500 ease-out"
                    style={{ transform: `translateX(-${current * 100}%)` }}
                >
                    {slides.map((src) => (
                        <a key={src} href="/gsm-tools" className="block w-full shrink-0">
                            <img src={src} alt="" className="aspect-[16/7] w-full bg-slate-950 object-contain" />
                        </a>
                    ))}
                </div>

                <button
                    type="button"
                    aria-label="Previous slide"
                    onClick={() => go(current - 1)}
                    className="absolute top-1/2 left-2 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white sm:flex"
                >
                    <ChevronLeft className="h-5 w-5" />
                </button>
                <button
                    type="button"
                    aria-label="Next slide"
                    onClick={() => go(current + 1)}
                    className="absolute top-1/2 right-2 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white sm:flex"
                >
                    <ChevronRight className="h-5 w-5" />
                </button>

                <div className="absolute inset-x-0 bottom-2 flex justify-center gap-1.5">
                    {slides.map((src, index) => (
                        <button
                            key={src}
                            type="button"
                            aria-label={`Slide ${index + 1}`}
                            onClick={() => go(index)}
                            className={`h-1.5 rounded-full transition-all ${index === current ? 'w-6 bg-white' : 'w-1.5 bg-white/50'}`}
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}
