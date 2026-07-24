<?php

namespace App\Http\Controllers;

use App\Services\BlogRepository;
use App\Services\Marketing\AlternativeRepository;
use App\Services\Marketing\GlossaryRepository;
use App\Services\Marketing\TemplateRepository;
use App\Services\Marketing\UseCaseRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function __invoke(
        BlogRepository $posts,
        UseCaseRepository $useCases,
        AlternativeRepository $alternatives,
        GlossaryRepository $glossary,
        TemplateRepository $templates,
    ): Response {
        $index = [
            ['loc' => url('/sitemap-core.xml'), 'lastmod' => now()->toDateString()],
            ['loc' => url('/sitemap-blog.xml'), 'lastmod' => $this->latestBlogDate($posts)],
            ['loc' => url('/sitemap-use-cases.xml'), 'lastmod' => now()->toDateString()],
            ['loc' => url('/sitemap-alternatives.xml'), 'lastmod' => now()->toDateString()],
            ['loc' => url('/sitemap-glossary.xml'), 'lastmod' => now()->toDateString()],
            ['loc' => url('/sitemap-templates.xml'), 'lastmod' => now()->toDateString()],
        ];

        $xml = view('website.sitemap-index', ['sitemaps' => $index])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function core(): Response
    {
        $today = now()->toDateString();

        $urls = [
            ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0', 'lastmod' => $today],
            ['loc' => url('/features'), 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
            ['loc' => url('/pricing'), 'changefreq' => 'weekly', 'priority' => '0.9', 'lastmod' => $today],
            ['loc' => url('/faq'), 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today],
            ['loc' => url('/contact'), 'changefreq' => 'monthly', 'priority' => '0.6', 'lastmod' => $today],
            ['loc' => url('/about'), 'changefreq' => 'monthly', 'priority' => '0.6', 'lastmod' => $today],
            ['loc' => url('/blog'), 'changefreq' => 'weekly', 'priority' => '0.8', 'lastmod' => $today],
            ['loc' => url('/use-cases'), 'changefreq' => 'weekly', 'priority' => '0.8', 'lastmod' => $today],
            ['loc' => url('/alternatives'), 'changefreq' => 'weekly', 'priority' => '0.8', 'lastmod' => $today],
            ['loc' => url('/glossary'), 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today],
            ['loc' => url('/templates'), 'changefreq' => 'weekly', 'priority' => '0.8', 'lastmod' => $today],
            ['loc' => url('/privacy'), 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
            ['loc' => url('/terms'), 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
            ['loc' => url('/refund'), 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
        ];

        return $this->urlset($urls);
    }

    public function blog(BlogRepository $posts): Response
    {
        $urls = collect($posts->all())->map(fn (array $post) => [
            'loc' => url('/blog/'.$post['slug']),
            'changefreq' => 'monthly',
            'priority' => '0.6',
            'lastmod' => $post['date'] ?? now()->toDateString(),
        ])->all();

        return $this->urlset($urls);
    }

    public function useCases(UseCaseRepository $useCases): Response
    {
        $today = now()->toDateString();
        $urls = collect($useCases->all())->map(fn (array $item) => [
            'loc' => url('/use-cases/'.$item['slug']),
            'changefreq' => 'monthly',
            'priority' => '0.7',
            'lastmod' => $today,
        ])->all();

        return $this->urlset($urls);
    }

    public function alternatives(AlternativeRepository $alternatives): Response
    {
        $today = now()->toDateString();
        $urls = collect($alternatives->all())->flatMap(function (array $item) use ($today) {
            return [
                [
                    'loc' => url('/alternatives/'.$item['slug']),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                    'lastmod' => $today,
                ],
                [
                    'loc' => url('/compare/'.$item['slug'].'-vs-snapdraft'),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                    'lastmod' => $today,
                ],
            ];
        })->values()->all();

        return $this->urlset($urls);
    }

    public function glossary(GlossaryRepository $glossary): Response
    {
        $today = now()->toDateString();
        $urls = collect($glossary->all())->map(fn (array $item) => [
            'loc' => url('/glossary/'.$item['slug']),
            'changefreq' => 'monthly',
            'priority' => '0.6',
            'lastmod' => $today,
        ])->all();

        return $this->urlset($urls);
    }

    public function templates(TemplateRepository $templates): Response
    {
        $today = now()->toDateString();
        $urls = collect($templates->all())->map(fn (array $item) => [
            'loc' => url('/templates/'.$item['slug']),
            'changefreq' => 'monthly',
            'priority' => '0.7',
            'lastmod' => $today,
        ])->all();

        return $this->urlset($urls);
    }

    /**
     * @param  list<array{loc: string, changefreq?: string, priority?: string, lastmod?: string|null}>  $urls
     */
    protected function urlset(array $urls): Response
    {
        $xml = view('website.sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    protected function latestBlogDate(BlogRepository $posts): string
    {
        $dates = collect($posts->all())->pluck('date')->filter();

        if ($dates->isEmpty()) {
            return now()->toDateString();
        }

        return Carbon::parse($dates->max())->toDateString();
    }
}
