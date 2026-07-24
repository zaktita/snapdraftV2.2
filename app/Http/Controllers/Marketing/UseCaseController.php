<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\UseCaseRepository;
use Illuminate\View\View;

class UseCaseController extends Controller
{
    public function __construct(protected UseCaseRepository $useCases) {}

    public function index(): View
    {
        return view('website.use-cases.index', [
            'useCases' => $this->useCases->all(),
        ]);
    }

    public function show(string $slug): View
    {
        $useCase = $this->useCases->find($slug);
        abort_if($useCase === null, 404);

        return view('website.use-cases.show', [
            'useCase' => $useCase,
            'related' => collect($this->useCases->all())
                ->reject(fn (array $item) => $item['slug'] === $slug)
                ->values()
                ->all(),
        ]);
    }
}
