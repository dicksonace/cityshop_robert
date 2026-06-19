import CityShopBrand from '@/components/cityshop-brand';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-gradient-to-b from-gray-50 to-white p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <CityShopBrand showText size="lg" />

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-semibold text-gray-900">{title}</h1>
                            <p className="text-center text-sm text-gray-500">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
