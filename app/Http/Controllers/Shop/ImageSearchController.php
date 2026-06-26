<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\ImageSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImageSearchController extends Controller
{
    public function __construct(private ImageSearchService $imageSearch) {}

    public function create(): Response
    {
        return Inertia::render('shop/image-search', [
            'results' => [],
            'preview' => null,
            'keywords' => [],
            'method' => null,
            'visionEnabled' => (bool) config('services.openai.key'),
        ]);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $result = $this->imageSearch->search($validated['image']);

        $products = $result['products']->map(fn ($row) => [
            'product' => $row['product'],
            'match_percent' => $row['match_percent'],
        ])->values();

        return Inertia::render('shop/image-search', [
            'results' => $products,
            'preview' => $result['preview'],
            'keywords' => $result['keywords'],
            'method' => $result['method'],
            'visionEnabled' => (bool) config('services.openai.key'),
        ]);
    }
}
