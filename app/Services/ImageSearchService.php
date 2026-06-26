<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageSearchService
{
    private const BINS = 64;

    /**
     * @return array{products: Collection<int, array{product: Product, score: float, match_percent: int}>, preview: string, keywords: string[], method: string}
     */
    public function search(UploadedFile $file): array
    {
        $absolutePath = $file->getRealPath();
        $querySignature = $this->extractColorSignature($absolutePath);
        $keywords = $this->extractKeywordsViaVision($file);
        $preview = 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($absolutePath));

        $candidates = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop()
            ->get();

        $scored = [];

        foreach ($candidates as $product) {
            $visualScore = 0.0;

            foreach ($product->images as $image) {
                $signature = $this->resolveSignature($image);
                if ($signature) {
                    $visualScore = max($visualScore, $this->histogramSimilarity($querySignature, $signature));
                }
            }

            $textScore = $keywords ? $this->keywordMatchScore($product, $keywords) : 0.0;

            $finalScore = $keywords
                ? (0.55 * $visualScore) + (0.45 * $textScore)
                : $visualScore;

            if ($finalScore >= 0.12) {
                $scored[] = [
                    'product' => $product,
                    'score' => round($finalScore, 4),
                    'match_percent' => (int) round(min(99, $finalScore * 100)),
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $top = collect(array_slice($scored, 0, 48));

        return [
            'products' => $top,
            'preview' => $preview,
            'keywords' => $keywords ?? [],
            'method' => $keywords ? 'ai_visual' : 'visual',
        ];
    }

    public function indexImage(ProductImage $image): ?array
    {
        $contents = $this->readImageBytes($image->path);

        if ($contents === null) {
            return null;
        }

        $signature = $this->extractColorSignatureFromBytes($contents);

        if (array_sum($signature) <= 0) {
            return null;
        }

        $image->update(['color_signature' => $signature]);

        return $signature;
    }

    public function resolveSignature(ProductImage $image): ?array
    {
        if (is_array($image->color_signature) && count($image->color_signature) === self::BINS) {
            return $image->color_signature;
        }

        return $this->indexImage($image);
    }

    /**
     * @return array<int, float>
     */
    public function extractColorSignature(string $absolutePath): array
    {
        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            return array_fill(0, self::BINS, 0);
        }

        return $this->extractColorSignatureFromBytes($contents);
    }

    /**
     * @return array<int, float>
     */
    public function extractColorSignatureFromBytes(string $contents): array
    {
        $img = @imagecreatefromstring($contents);

        if (! $img) {
            return array_fill(0, self::BINS, 0);
        }

        $width = imagesx($img);
        $height = imagesy($img);
        $bins = array_fill(0, self::BINS, 0.0);

        $step = max(1, (int) floor(min($width, $height) / 40));

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $idx = (int) ($r / 64) * 16 + (int) ($g / 64) * 4 + (int) ($b / 64);
                $bins[$idx] += 1;
            }
        }

        imagedestroy($img);

        $total = array_sum($bins) ?: 1;

        return array_map(fn ($v) => $v / $total, $bins);
    }

    private function readImageBytes(string $path): ?string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            try {
                $response = Http::timeout(20)->get($path);

                return $response->successful() ? $response->body() : null;
            } catch (\Throwable) {
                return null;
            }
        }

        $localPath = Storage::disk('public')->path($path);

        if (! is_file($localPath)) {
            return null;
        }

        $contents = @file_get_contents($localPath);

        return $contents === false ? null : $contents;
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function histogramSimilarity(array $a, array $b): float
    {
        $sum = 0.0;

        for ($i = 0; $i < self::BINS; $i++) {
            $sum += min($a[$i] ?? 0, $b[$i] ?? 0);
        }

        return $sum;
    }

    /**
     * @param  string[]  $keywords
     */
    public function keywordMatchScore(Product $product, array $keywords): float
    {
        if ($keywords === []) {
            return 0.0;
        }

        $haystack = Str::lower(implode(' ', array_filter([
            $product->name,
            $product->description,
            $product->brand,
            $product->meta_keywords,
            $product->category?->name,
        ])));

        $hits = 0;

        foreach ($keywords as $keyword) {
            $keyword = Str::lower(trim($keyword));
            if ($keyword !== '' && Str::contains($haystack, $keyword)) {
                $hits++;
            }
        }

        return $hits / count($keywords);
    }

    /**
     * @return string[]|null
     */
    public function extractKeywordsViaVision(UploadedFile $file): ?array
    {
        $apiKey = config('services.openai.key');

        if (! $apiKey) {
            return null;
        }

        try {
            $mime = $file->getMimeType() ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($file->getRealPath()));

            $response = Http::timeout(45)
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.vision_model', 'gpt-4o-mini'),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Identify this product for an e-commerce search. Reply ONLY with JSON: {"keywords":["up to 8 short search terms"],"product_type":"brief label"}. Focus on item type, color, brand if visible, material.',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => ['url' => "data:{$mime};base64,{$base64}"],
                                ],
                            ],
                        ],
                    ],
                    'max_tokens' => 180,
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI vision failed', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $content = $response->json('choices.0.message.content', '');
            $json = $this->parseJsonFromText($content);

            if (! $json || empty($json['keywords'])) {
                return null;
            }

            return array_values(array_unique(array_filter(array_map(
                fn ($k) => Str::lower(trim((string) $k)),
                (array) $json['keywords']
            ))));
        } catch (\Throwable $e) {
            Log::warning('OpenAI vision exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonFromText(string $text): ?array
    {
        $text = trim($text);

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
