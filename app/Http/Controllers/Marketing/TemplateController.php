<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\TemplateRepository;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function __construct(protected TemplateRepository $templates) {}

    public function index(): View
    {
        return view('website.templates.index', [
            'templates' => $this->templates->all(),
        ]);
    }

    public function show(string $slug): View
    {
        $template = $this->templates->find($slug);
        abort_if($template === null, 404);

        return view('website.templates.show', [
            'template' => $template,
            'related' => collect($this->templates->all())
                ->reject(fn (array $item) => $item['slug'] === $slug)
                ->take(3)
                ->values()
                ->all(),
        ]);
    }
}
