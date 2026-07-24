<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * File-based blog: posts are markdown files with frontmatter in
 * resources/content/blog/. The filename (without extension) is the slug.
 */
class BlogRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $dir = $this->directory();

        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($file) => $file->getExtension() === 'md')
            ->map(fn ($file) => $this->parse($file->getPathname(), $file->getFilenameWithoutExtension()))
            ->filter()
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$slug.'.md';

        if (! File::exists($path)) {
            return null;
        }

        $post = $this->parse($path, $slug);

        if ($post !== null) {
            $post['html'] = Str::markdown($post['body'], [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        return $post;
    }

    protected function directory(): string
    {
        return resource_path('content/blog');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parse(string $path, string $slug): ?array
    {
        $raw = File::get($path);

        if (! preg_match('/^---\R(.*?)\R---\R(.*)$/s', $raw, $matches)) {
            return null;
        }

        $meta = [];
        foreach (preg_split('/\R/', trim($matches[1])) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $meta[trim($key)] = trim(trim($value), '"\'');
            }
        }

        $date = isset($meta['date']) ? Carbon::parse($meta['date']) : null;

        return [
            'slug' => $slug,
            'title' => $meta['title'] ?? Str::headline($slug),
            'excerpt' => $meta['excerpt'] ?? '',
            'cover' => $meta['cover'] ?? null,
            'category' => $meta['category'] ?? 'News',
            'related_slugs' => array_values(array_filter(array_map('trim', explode(',', $meta['related'] ?? '')))),
            'date' => $date?->toDateString(),
            'date_iso' => $date?->toIso8601String(),
            'date_formatted' => $date?->format('M j, Y'),
            'reading_minutes' => max(1, (int) ceil(str_word_count(strip_tags($matches[2])) / 200)),
            'body' => trim($matches[2]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function related(string $slug, int $limit = 3): array
    {
        $current = $this->find($slug);
        $all = $this->all();

        if ($current !== null && ! empty($current['related_slugs'])) {
            $picked = [];
            foreach ($current['related_slugs'] as $relatedSlug) {
                foreach ($all as $post) {
                    if ($post['slug'] === $relatedSlug) {
                        $picked[] = collect($post)->except('body')->all();
                        break;
                    }
                }
            }
            if (count($picked) >= $limit) {
                return array_slice($picked, 0, $limit);
            }
        }

        return collect($all)
            ->reject(fn (array $post) => $post['slug'] === $slug)
            ->when(
                $current !== null,
                fn ($c) => $c->sortByDesc(fn (array $post) => ($post['category'] ?? '') === ($current['category'] ?? '') ? 1 : 0)
            )
            ->take($limit)
            ->map(fn (array $post) => collect($post)->except('body')->all())
            ->values()
            ->all();
    }
}
