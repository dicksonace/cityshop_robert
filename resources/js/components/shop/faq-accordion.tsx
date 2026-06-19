import { useState } from 'react';
import { ChevronDown } from 'lucide-react';

import { cn } from '@/lib/utils';

export interface FaqItem {
    question: string;
    answer: string;
}

export interface FaqCategory {
    category: string;
    items: FaqItem[];
}

interface FaqAccordionProps {
    sections: FaqCategory[];
}

export default function FaqAccordion({ sections }: FaqAccordionProps) {
    const [openKey, setOpenKey] = useState<string | null>(null);

    const toggle = (key: string) => {
        setOpenKey((current) => (current === key ? null : key));
    };

    return (
        <div className="space-y-8">
            {sections.map((section) => (
                <section key={section.category}>
                    <h2 className="mb-4 text-lg font-bold text-gray-900">{section.category}</h2>
                    <div className="space-y-2">
                        {section.items.map((item, index) => {
                            const key = `${section.category}-${index}`;
                            const isOpen = openKey === key;

                            return (
                                <div
                                    key={key}
                                    className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm"
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggle(key)}
                                        className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors hover:bg-orange-50/50"
                                        aria-expanded={isOpen}
                                    >
                                        <span className="font-medium text-gray-900">{item.question}</span>
                                        <ChevronDown
                                            className={cn(
                                                'h-5 w-5 shrink-0 text-orange-500 transition-transform',
                                                isOpen && 'rotate-180',
                                            )}
                                        />
                                    </button>
                                    {isOpen && (
                                        <div className="border-t border-gray-50 px-5 py-4 text-sm leading-relaxed text-gray-600">
                                            {item.answer}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );
}
