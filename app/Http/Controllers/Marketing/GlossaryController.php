<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\GlossaryRepository;
use Illuminate\View\View;

class GlossaryController extends Controller
{
    public function __construct(protected GlossaryRepository $glossary) {}

    public function index(): View
    {
        return view('website.glossary.index', [
            'terms' => $this->glossary->all(),
        ]);
    }

    public function show(string $slug): View
    {
        $term = $this->glossary->find($slug);
        abort_if($term === null, 404);

        return view('website.glossary.show', [
            'term' => $term,
            'others' => collect($this->glossary->all())
                ->reject(fn (array $item) => $item['slug'] === $slug)
                ->values()
                ->all(),
        ]);
    }
}
