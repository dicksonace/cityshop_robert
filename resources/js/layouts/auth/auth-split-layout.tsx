import CityShopBrand from '@/components/cityshop-brand';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { quote } = usePage<SharedData>().props;

    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col p-10 text-white lg:flex">
                <div className="absolute inset-0 bg-gradient-to-br from-blue-600 via-blue-700 to-orange-600" />
                <div className="relative z-20">
                    <CityShopBrand showText size="lg" inverted />
                </div>
                <p className="relative z-20 mt-6 max-w-sm text-blue-100">
                    Ghana&apos;s trusted marketplace for buyers and sellers. Shop with confidence.
                </p>
                {quote && (
                    <div className="relative z-20 mt-auto">
                        <blockquote className="space-y-2">
                            <p className="text-lg text-white/90">&ldquo;{quote.message}&rdquo;</p>
                            <footer className="text-sm text-blue-200">{quote.author}</footer>
                        </blockquote>
                    </div>
                )}
            </div>
            <div className="w-full bg-white lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <CityShopBrand showText size="lg" className="justify-center lg:hidden" />

                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-semibold text-gray-900">{title}</h1>
                        <p className="text-sm text-balance text-gray-500">{description}</p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
