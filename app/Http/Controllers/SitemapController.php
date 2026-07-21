<?php

namespace App\Http\Controllers;

use App\Services\BlogRepository;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(BlogRepository $posts): Response
    {
        $static = [
            ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => url('/features'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => url('/pricing'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => url('/faq'), 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => url('/contact'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => url('/blog'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/privacy'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => url('/terms'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => url('/refund'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => url('/beta/apply'), 'changefreq' => 'monthly', 'priority' => '0.5'],
        ];

        $blog = collect($posts->all())->map(fn (array $post) => [
            'loc' => url('/blog/'.$post['slug']),
            'changefreq' => 'monthly',
            'priority' => '0.6',
            'lastmod' => $post['date'] ?? null,
        ]);

        $urls = collect($static)->merge($blog);

        $xml = view('website.sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
