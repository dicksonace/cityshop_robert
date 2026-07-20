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

    /** Max bit differences allowed between query and product image (0 = identical). */
    private const MAX_DHASH_DISTANCE = 8;

    /** Minimum combined visual score (0–1) to count as a match. */
    private const MIN_MATCH_SCORE = 0.82;

    /** If the best candidate is below this, return nothing. */
    private const MIN_BEST_SCORE = 0.85;

    private const MAX_RESULTS = 24;

    /**
     * @return array{products: Collection<int, array{product: Product, score: float, match_percent: int}>, preview: string, keywords: string[], method: string}
     */
    public function search(UploadedFile $file, ?int $sellerId = null): array
    {
        $contents = file_get_contents($file->getRealPath());
        $querySignature = $this->buildSignatureFromBytes($contents ?: '');
        $keywords = $this->extractKeywordsViaVision($file);
        $preview = 'data:'.$file->getMimeType().';base64,'.base64_encode($contents ?: '');

        if (! $this->isValidSignature($querySignature)) {
            return $this->emptyResult($preview, $keywords);
        }

        $candidatesQuery = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop();

        if ($sellerId) {
            $candidatesQuery->where('seller_id', $sellerId);
        }

        $candidates = $candidatesQuery->get();

        $scored = [];

        foreach ($candidates as $product) {
            $bestImageScore = 0.0;

            foreach ($product->images as $image) {
                $signature = $this->resolveSignature($image);

                if (! $this->isValidSignature($signature)) {
                    continue;
                }

                $imageScore = $this->compareSignatures($querySignature, $signature);
                $bestImageScore = max($bestImageScore, $imageScore);
            }

            if ($bestImageScore < self::MIN_MATCH_SCORE) {
                continue;
            }

            $textScore = $keywords ? $this->keywordMatchScore($product, $keywords) : 0.0;

            // Visual match is required; AI keywords only help rank among already-similar images.
            $finalScore = $keywords && $textScore > 0
                ? min(1.0, ($bestImageScore * 0.85) + ($textScore * 0.15))
                : $bestImageScore;

            $scored[] = [
                'product' => $product,
                'score' => round($finalScore, 4),
                'match_percent' => $this->scoreToPercent($bestImageScore),
            ];
        }

        if ($scored === []) {
            return $this->emptyResult($preview, $keywords);
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $bestScore = $scored[0]['score'];

        if ($bestScore < self::MIN_BEST_SCORE) {
            return $this->emptyResult($preview, $keywords);
        }

        // Only keep results close to the best match — drop weak tail.
        $cutoff = $bestScore * 0.94;
        $filtered = array_values(array_filter($scored, fn ($row) => $row['score'] >= $cutoff));

        return [
            'products' => collect(array_slice($filtered, 0, self::MAX_RESULTS)),
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

        $signature = $this->buildSignatureFromBytes($contents);

        if (! $this->isValidSignature($signature)) {
            return null;
        }

        $image->update(['color_signature' => $signature]);

        return $signature;
    }

    /**
     * @return array{histogram: array<int, float>, dhash: string}|null
     */
    public function resolveSignature(ProductImage $image): ?array
    {
        $stored = $image->color_signature;

        if ($this->isValidSignature($stored)) {
            return $stored;
        }

        return $this->indexImage($image);
    }

    /**
     * @param  array<int, float>|array{histogram?: array<int, float>, dhash?: string}|null  $signature
     */
    public function isValidSignature(?array $signature): bool
    {
        if (! is_array($signature)) {
            return false;
        }

        $histogram = $signature['histogram'] ?? null;
        $dhash = $signature['dhash'] ?? null;

        return is_array($histogram)
            && count($histogram) === self::BINS
            && is_string($dhash)
            && strlen($dhash) === self::BINS;
    }

    /**
     * @return array{histogram: array<int, float>, dhash: string}
     */
    public function buildSignatureFromBytes(string $contents): array
    {
        return [
            'histogram' => $this->extractColorHistogramFromBytes($contents),
            'dhash' => $this->extractDHashFromBytes($contents),
        ];
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

        return $this->extractColorHistogramFromBytes($contents);
    }

    /**
     * @return array<int, float>
     */
    public function extractColorHistogramFromBytes(string $contents): array
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

    public function extractDHashFromBytes(string $contents): string
    {
        $img = @imagecreatefromstring($contents);

        if (! $img) {
            return str_repeat('0', self::BINS);
        }

        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 9, 8, imagesx($img), imagesy($img));
        imagedestroy($img);

        $gray = [];

        for ($y = 0; $y < 8; $y++) {
            $gray[$y] = [];
            for ($x = 0; $x < 9; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray[$y][$x] = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
            }
        }

        imagedestroy($small);

        $hash = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $hash .= $gray[$y][$x] > $gray[$y][$x + 1] ? '1' : '0';
            }
        }

        return $hash;
    }

    /**
     * @param  array{histogram: array<int, float>, dhash: string}  $a
     * @param  array{histogram: array<int, float>, dhash: string}  $b
     */
    public function compareSignatures(array $a, array $b): float
    {
        $distance = $this->hammingDistance($a['dhash'], $b['dhash']);

        if ($distance > self::MAX_DHASH_DISTANCE) {
            return 0.0;
        }

        $structureScore = 1.0 - ($distance / self::BINS);
        $colorScore = $this->histogramSimilarity($a['histogram'], $b['histogram']);

        // Structure (dHash) matters most; color confirms the match.
        return (0.75 * $structureScore) + (0.25 * $colorScore);
    }

    public function hammingDistance(string $a, string $b): int
    {
        $distance = 0;
        $length = min(strlen($a), strlen($b));

        for ($i = 0; $i < $length; $i++) {
            if ($a[$i] !== $b[$i]) {
                $distance++;
            }
        }

        return $distance + abs(strlen($a) - strlen($b));
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

    private function scoreToPercent(float $score): int
    {
        return (int) round(min(99, max(0, $score * 100)));
    }

    /**
     * @param  string[]|null  $keywords
     * @return array{products: Collection, preview: string, keywords: string[], method: string}
     */
    private function emptyResult(string $preview, ?array $keywords): array
    {
        return [
            'products' => collect(),
            'preview' => $preview,
            'keywords' => $keywords ?? [],
            'method' => $keywords ? 'ai_visual' : 'visual',
        ];
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
