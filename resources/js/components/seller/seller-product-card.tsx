import { Link } from '@inertiajs/react';
import { BarChart3, Eye, MoreHorizontal, Pencil, Copy, EyeOff, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatPrice, Product, productImageUrl } from '@/types/marketplace';

interface SellerProductCardProps {
    product: Product & { reviews_count?: number; category?: { name: string } };
    onDuplicate?: (id: number) => void;
    onToggleVisibility?: (id: number) => void;
    onDelete?: (id: number) => void;
}

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
    draft: 'bg-gray-100 text-gray-700',
};

export default function SellerProductCard({ product, onDuplicate, onToggleVisibility, onDelete }: SellerProductCardProps) {
    const image = product.images?.[0];
    const price = product.discount_price ?? product.price;
    const soldOut = product.quantity === 0 && !product.is_preorder;

    return (
        <div className="group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-orange-100 hover:shadow-md">
            <div className="relative aspect-[4/3] bg-gradient-to-br from-slate-50 to-orange-50/20 p-4">
                <img
                    src={productImageUrl(image?.path)}
                    alt={product.name}
                    className="mx-auto h-full max-h-full w-full object-contain"
                />
                <span className={`absolute top-3 left-3 rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize ${statusStyles[product.status] ?? statusStyles.draft}`}>
                    {product.status}
                </span>
                {soldOut && (
                    <span className="absolute top-3 right-3 rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">Sold out</span>
                )}
            </div>

            <div className="p-4">
                <p className="text-[10px] font-semibold uppercase tracking-wide text-blue-500">{product.category?.name ?? 'Uncategorized'}</p>
                <h3 className="mt-0.5 line-clamp-2 font-semibold text-gray-900">{product.name}</h3>

                <div className="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                    <div className="rounded-lg bg-gray-50 py-2">
                        <p className="font-bold text-gray-900">{formatPrice(price)}</p>
                        <p className="text-gray-400">Price</p>
                    </div>
                    <div className="rounded-lg bg-gray-50 py-2">
                        <p className="font-bold text-gray-900">{product.quantity}</p>
                        <p className="text-gray-400">Stock</p>
                    </div>
                    <div className="rounded-lg bg-gray-50 py-2">
                        <p className="font-bold text-gray-900">{product.views ?? 0}</p>
                        <p className="text-gray-400">Views</p>
                    </div>
                </div>

                <div className="mt-3 flex items-center justify-between gap-2">
                    <Link href={route('seller.products.edit', product.id)} className="flex-1">
                        <Button variant="outline" size="sm" className="w-full">
                            <Pencil className="mr-1 h-3.5 w-3.5" />
                            Edit
                        </Button>
                    </Link>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="icon" className="h-8 w-8 shrink-0">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {product.status === 'approved' && product.slug && (
                                <DropdownMenuItem asChild>
                                    <a href={route('products.show', product.slug)} target="_blank" rel="noreferrer">
                                        <Eye className="mr-2 h-4 w-4" /> View live
                                    </a>
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem asChild>
                                <Link href={route('seller.products.analytics', product.id)}>
                                    <BarChart3 className="mr-2 h-4 w-4" /> Analytics
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onDuplicate?.(product.id)}>
                                <Copy className="mr-2 h-4 w-4" /> Duplicate
                            </DropdownMenuItem>
                            {(product.status === 'approved' || product.status === 'draft') && (
                                <DropdownMenuItem onClick={() => onToggleVisibility?.(product.id)}>
                                    {product.status === 'draft' ? (
                                        <><Eye className="mr-2 h-4 w-4" /> Submit for review</>
                                    ) : (
                                        <><EyeOff className="mr-2 h-4 w-4" /> Hide listing</>
                                    )}
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem className="text-red-600" onClick={() => onDelete?.(product.id)}>
                                <Trash2 className="mr-2 h-4 w-4" /> Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    );
}
