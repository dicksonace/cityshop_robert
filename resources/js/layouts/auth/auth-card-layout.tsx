import CityShopBrand from '@/components/cityshop-brand';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function AuthCardLayout({
    children,
    title,
    description,
}: {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-gradient-to-b from-gray-50 to-white p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <CityShopBrand showText size="lg" className="self-center" />

                <div className="flex flex-col gap-6">
                    <Card className="rounded-xl border-gray-100 shadow-sm">
                        <CardHeader className="px-10 pt-8 pb-0 text-center">
                            <CardTitle className="text-xl">{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8">{children}</CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
