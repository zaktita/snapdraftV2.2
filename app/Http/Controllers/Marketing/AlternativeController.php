<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\AlternativeRepository;
use Illuminate\View\View;

class AlternativeController extends Controller
{
    public function __construct(protected AlternativeRepository $alternatives) {}

    public function index(): View
    {
        return view('website.alternatives.index', [
            'alternatives' => $this->alternatives->all(),
        ]);
    }

    public function show(string $slug): View
    {
        $alternative = $this->alternatives->find($slug);
        abort_if($alternative === null, 404);

        return view('website.alternatives.show', [
            'alternative' => $alternative,
            'related' => collect($this->alternatives->all())
                ->reject(fn (array $item) => $item['slug'] === $slug)
                ->take(4)
                ->values()
                ->all(),
        ]);
    }

    public function compare(string $slug): View
    {
        $alternative = $this->alternatives->find($slug);
        abort_if($alternative === null, 404);

        return view('website.alternatives.compare', [
            'alternative' => $alternative,
        ]);
    }
}
