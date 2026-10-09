import { useEffect, useState } from 'react';

type Slide = { id: number; image_url: string };

export default function PlaceOrderSlider({ slides }: { slides: Slide[] }) {
    const [current, setCurrent] = useState(0);

    useEffect(() => {
        if (slides.length < 2) return;
        const timer = window.setInterval(() => {
            setCurrent((prev) => (prev + 1) % slides.length);
        }, 4500);
        return () => window.clearInterval(timer);
    }, [slides.length]);

    if (slides.length === 0) return null;

    return (
        <div className="relative mt-3 overflow-hidden rounded-2xl bg-slate-950">
            <div
                className="flex transition-transform duration-500 ease-out"
                style={{ transform: `translateX(-${current * 100}%)` }}
            >
                {slides.map((slide) => (
                    <img
                        key={slide.id}
                        src={slide.image_url}
                        alt=""
                        className="aspect-[16/7] w-full shrink-0 bg-slate-950 object-contain"
                    />
                ))}
            </div>
            {slides.length > 1 ? (
                <div className="absolute inset-x-0 bottom-2 flex justify-center gap-1.5">
                    {slides.map((slide, index) => (
                        <button
                            key={slide.id}
                            type="button"
                            aria-label={`Slide ${index + 1}`}
                            onClick={() => setCurrent(index)}
                            className={`h-1.5 rounded-full ${index === current ? 'w-6 bg-white' : 'w-1.5 bg-white/50'}`}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}
