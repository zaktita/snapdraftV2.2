<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\BlogRepository;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function __construct(protected BlogRepository $posts) {}

    public function index(): View
    {
        $posts = collect($this->posts->all())
            ->map(fn (array $post) => collect($post)->except('body')->all())
            ->values()
            ->all();

        return view('website.blog.index', [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug): View
    {
        $post = $this->posts->find($slug);

        abort_if($post === null, 404);

        return view('website.blog.show', [
            'post' => collect($post)->except('body')->all(),
            'related' => $this->posts->related($slug, 3),
        ]);
    }
}
