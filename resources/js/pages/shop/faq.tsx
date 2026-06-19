import { Head, Link } from '@inertiajs/react';
import { HelpCircle, Mail, MessageCircle } from 'lucide-react';

import FaqAccordion, { FaqCategory } from '@/components/shop/faq-accordion';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';

interface ContactInfo {
    email: string;
    whatsapp: string;
}

interface FaqProps {
    faq: FaqCategory[];
    contact: ContactInfo;
}

export default function Faq({ faq, contact }: FaqProps) {
    return (
        <ShopLayout>
            <Head title="FAQ" />
            <div className="mx-auto max-w-3xl px-4 py-10">
                <div className="text-center">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-orange-50">
                        <HelpCircle className="h-7 w-7 text-orange-500" />
                    </div>
                    <h1 className="mt-4 text-3xl font-bold text-gray-900">Frequently Asked Questions</h1>
                    <p className="mt-2 text-gray-500">Everything you need to know about buying and selling on CityShop.</p>
                </div>

                <div className="mt-10">
                    <FaqAccordion sections={faq} />
                </div>

                <div className="mt-12 rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-sm">
                    <h2 className="text-lg font-semibold text-gray-900">Still have questions?</h2>
                    <p className="mt-2 text-sm text-gray-500">Our support team is ready to help you.</p>
                    <div className="mt-6 flex flex-wrap justify-center gap-3">
                        <Button asChild className="bg-orange-500 hover:bg-orange-600">
                            <Link href={route('contact')}>
                                <Mail className="mr-2 h-4 w-4" />
                                Contact Support
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={`https://wa.me/${contact.whatsapp.replace(/\D/g, '')}`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MessageCircle className="mr-2 h-4 w-4" />
                                WhatsApp Us
                            </a>
                        </Button>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
