<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\BlogRepository;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(protected BlogRepository $posts) {}

    public function index(): Response
    {
        $posts = collect($this->posts->all())
            ->map(fn (array $post) => collect($post)->except('body')->all())
            ->values()
            ->all();

        return Inertia::render('website/blog/index', [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug): Response
    {
        $post = $this->posts->find($slug);

        abort_if($post === null, 404);

        return Inertia::render('website/blog/show', [
            'post' => collect($post)->except('body')->all(),
        ]);
    }
}
