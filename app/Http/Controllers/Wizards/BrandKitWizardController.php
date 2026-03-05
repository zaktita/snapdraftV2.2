<?php

namespace App\Http\Controllers\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BrandKitWizardController extends Controller
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Show the brand kit wizard form.
     */
    public function index()
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/wizards/brand-kit');
    }

    /**
     * Process the brand kit wizard form and generate the brand board.
     */
    public function store(Request $request)
    {
        set_time_limit(300);

        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'brand_name'    => 'required|string|max:255',
            'industry'      => 'required|string|max:255',
            'description'   => 'required|string|max:2000',
            'brand_feeling' => 'required|string|max:500',
            'colors'        => 'nullable|array|max:3',
            'colors.*'      => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'logo'          => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        $hasLogo = $request->hasFile('logo');
        $logoPath = null;

        if ($hasLogo) {
            $logoPath = $request->file('logo')->store('brand-kit-logos', 'public');
        }

        // ── Phase 1: Use Gemini Flash to expand user info into a full brand kit prompt ──
        $fullPrompt = $this->buildFullPromptViaAI($validated, $hasLogo);

        // ── Phase 2: Create project + dispatch image generation ──
        $project = Auth::user()->projects()->create([
            'name'        => $validated['brand_name'] . ' Brand Kit',
            'title'       => $validated['brand_name'] . ' Brand Kit',
            'description' => $validated['description'],
            'format'      => '1:1',
            'settings'    => [
                'wizard_type'    => 'brand-kit',
                'brand_name'     => $validated['brand_name'],
                'industry'       => $validated['industry'],
                'brand_feeling'  => $validated['brand_feeling'],
                'colors'         => $validated['colors'] ?? [],
                'has_logo'       => $hasLogo,
                'logo_path'      => $logoPath,
                'format'         => '1:1',
                'generated_prompt' => $fullPrompt,
            ],
        ]);

        $aiModel = config('services.gemini.text_accurate_model', 'gemini-3-pro-image-preview');

        // ── If a logo was uploaded, store it as a brand reference so the job sends it to Gemini ──
        if ($logoPath) {
            $project->brandReferences()->create([
                'url'   => $logoPath,
                'order' => 0,
            ]);
        }

        $generation = $project->generationHistory()->create([
            'user_id'    => Auth::id(),
            'prompt'     => $fullPrompt,
            'ai_model'   => $aiModel,
            'status'     => 'pending',
            'parameters' => [
                'format'      => '1:1',
                'wizard_type' => 'brand-kit',
            ],
        ]);

        if (app()->environment('local')) {
            \App\Jobs\GenerateSingleImageJob::dispatchSync(
                project: $project,
                prompt: $fullPrompt,
                format: '1:1',
                generationId: $generation->id,
                title: $validated['brand_name'] . ' Brand Kit',
                description: $validated['description'],
                useSimplePrompt: false,
            );
        } else {
            \App\Jobs\GenerateSingleImageJob::dispatch(
                project: $project,
                prompt: $fullPrompt,
                format: '1:1',
                generationId: $generation->id,
                title: $validated['brand_name'] . ' Brand Kit',
                description: $validated['description'],
                useSimplePrompt: false,
            );
        }

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Brand kit is being generated!');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Call Gemini Flash (text-only) to expand the user's basic brand info into
     * a complete, detailed brand-kit image-generation prompt.
     */
    private function buildFullPromptViaAI(array $brandInfo, bool $hasLogo): string
    {
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            Log::warning('BrandKitWizard: No Gemini API key, falling back to basic prompt builder');
            return $this->buildFallbackPrompt($brandInfo, $hasLogo);
        }

        // Use the configured text/analysis model for Phase 1 prompt expansion
        $textModel = config('services.gemini.model', 'gemini-1.5-flash');

        $colorsInfo = $this->formatColorsForPrompt($brandInfo['colors'] ?? []);
        $template   = $hasLogo ? 'HAS_LOGO' : 'NO_LOGO';

        $systemPrompt = <<<SYSTEM
You are a world-class brand strategist and visual designer. Your task is to fill in the variables of a brand kit image generation prompt based on the brand information provided.

You must output ONLY the final filled-in image generation prompt — no explanations, no preamble, no markdown fences. Just the raw prompt text.

Template variant: {$template}

Brand information provided:
- Brand Name: {$brandInfo['brand_name']}
- Industry/Niche: {$brandInfo['industry']}
- Brand Description: {$brandInfo['description']}
- Brand Feeling/Vibe: {$brandInfo['brand_feeling']}
- Brand Colors: {$colorsInfo}
- Has Logo: {$template}

Based on this brand information, infer ALL missing variables (typography style, photo subjects, lighting style, color grading, textures, accent objects, background colors, brand inspirations, mood descriptors, etc.) to perfectly match the brand's personality.

SYSTEM;

        if ($hasLogo) {
            $promptTemplate = $this->getHasLogoTemplate($brandInfo, $colorsInfo);
        } else {
            $promptTemplate = $this->getNoLogoTemplate($brandInfo, $colorsInfo);
        }

        $userMessage = $systemPrompt . "\n\nHere is the template to fill in (replace all [bracketed] variables with real values based on the brand):\n\n" . $promptTemplate;

        try {
            $response = Http::withOptions(['timeout' => 60])->post(
                "{$this->baseUrl}/models/{$textModel}:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        [
                            'role'  => 'user',
                            'parts' => [['text' => $userMessage]],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature'       => 0.7,
                        'maxOutputTokens'   => 4096,
                        'responseModalities' => ['TEXT'],
                    ],
                ]
            );

            if (!$response->successful()) {
                Log::error('BrandKitWizard: Gemini text call failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return $this->buildFallbackPrompt($brandInfo, $hasLogo);
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            if (!$text) {
                Log::warning('BrandKitWizard: Gemini returned empty text');
                return $this->buildFallbackPrompt($brandInfo, $hasLogo);
            }

            // Strip any accidental markdown code fences
            $text = preg_replace('/^```[a-z]*\n?/im', '', $text);
            $text = preg_replace('/\n?```$/im', '', $text);

            Log::info('BrandKitWizard: AI prompt generated', [
                'brand'  => $brandInfo['brand_name'],
                'length' => strlen($text),
            ]);

            return trim($text);
        } catch (\Exception $e) {
            Log::error('BrandKitWizard: Exception during AI prompt building', [
                'error' => $e->getMessage(),
            ]);
            return $this->buildFallbackPrompt($brandInfo, $hasLogo);
        }
    }

    /**
     * Format colors array for the AI prompt.
     */
    private function formatColorsForPrompt(array $colors): string
    {
        $filtered = array_filter($colors);
        if (empty($filtered)) {
            return 'Not specified (infer from brand feeling and industry)';
        }

        $lines = [];
        foreach (array_values($filtered) as $i => $hex) {
            $lines[] = 'Color ' . ($i + 1) . ': ' . strtoupper($hex);
        }

        return implode(', ', $lines);
    }

    /**
     * Return the "no logo" template with brand-specific placeholders pre-filled.
     */
    private function getNoLogoTemplate(array $brandInfo, string $colorsInfo): string
    {
        $name = $brandInfo['brand_name'];

        return <<<TMPL
Premium [Industry/Niche] brand kit board for brand "{$name}", [Overall Vibe/Mood] identity presentation, vertical bento grid layout, clean editorial composition, [Specific Aesthetic style] branding, [Typography Style] typography, structured high-contrast negative space, [Mood Descriptor] brand guidelines board,

top module full width showing centered "{$name}" logo ([Logo Description, e.g., wordmark only, minimalist icon + wordmark]) on a [Background Color 1] background, thin [Accent Color] divider line, subtle [Texture Type] texture overlay,

second row split into two equal vertical bento blocks:

left block typography system showing [Headline Style] headline example, subheading example, short body text paragraph, alphabet preview A–Z, [Text Color] typography on a [Background Color 2] background, [Spacing Style] spacing,

right block color palette showing three stacked color swatches labeled with color names and hex codes:
[Color Name 1] [Hex Code 1],
[Color Name 2] [Hex Code 2],
[Color Name 3] [Hex Code 3],
minimal [Theme] labeling, small [Theme-related Object/Accent] accent overlapping swatch,

third module full width lifestyle photography strip showing four evenly spaced images featuring [Photo Subject 1], [Photo Subject 2], [Photo Subject 3], [Photo Subject 4], [Lighting Style], cohesive [Color Grading Style] color grading, rounded corners,

fourth module full width showing "{$name}" logo adaptability across three horizontal background blocks:
[Background Color 1] background with [Logo Color A],
[Background Color 2] background with [Logo Color B],
[Background Color 3] background with [Logo Color C],
small technical editorial captions under each block,

premium [Industry] brand guideline aesthetic, inspired by [Brand Inspiration 1], [Brand Inspiration 2], [Brand Inspiration 3], minimal swiss editorial layout, high-end branding, clean bento UI containers, crisp drop shadows, rounded modules, structured spacing, [Mood/Tonal Ending Phrase],

premium print brand board, ultra clean composition, modern [Overall Vibe] identity presentation
TMPL;
    }

    /**
     * Return the "has logo" template with brand-specific placeholders pre-filled.
     */
    private function getHasLogoTemplate(array $brandInfo, string $colorsInfo): string
    {
        $name = $brandInfo['brand_name'];

        return <<<TMPL
Premium [Industry] brand kit board for brand "{$name}", [Overall Vibe] identity presentation, vertical bento grid layout, clean editorial composition, [Aesthetic style] branding, [Typography Style] typography, structured high-contrast negative space, [Mood] brand guidelines board,

top module full width showing the provided logo image reference centered crisply on a [Background Color 1] background, thin [Accent Color] divider line, subtle [Texture Type] texture overlay,

second row split into two equal vertical bento blocks: left block typography system showing headline example, subheading, body text, alphabet A–Z in [Text Color] on a [Background Color 2] background. right block color palette showing three stacked color swatches: [Color Name 1/Hex], [Color Name 2/Hex], [Color Name 3/Hex], minimal technical labeling,

third module full width lifestyle photography strip showing four evenly spaced images featuring [Photo Subjects 1-4], [Lighting Style], cohesive [Color Grading Style], rounded corners,

fourth module full width showing the provided logo adaptability across three horizontal background blocks: [Background Color 1], [Background Color 2], and [Background Color 3], small technical editorial captions under each block,

premium [Industry] brand guideline aesthetic, inspired by [Brand Inspirations], clean bento UI containers, crisp drop shadows, rounded modules, structured spacing, premium print brand board, exactly matching the provided image reference logo.
TMPL;
    }

    /**
     * Fallback: build a reasonable prompt directly from user inputs without AI.
     */
    private function buildFallbackPrompt(array $brandInfo, bool $hasLogo): string
    {
        $name       = $brandInfo['brand_name'];
        $industry   = $brandInfo['industry'];
        $feeling    = $brandInfo['brand_feeling'];
        $colors     = $this->formatColorsForPrompt($brandInfo['colors'] ?? []);
        $description = $brandInfo['description'];

        $base = "Premium {$industry} brand kit board for brand \"{$name}\", {$feeling} identity presentation, ";
        $base .= "vertical bento grid layout, clean editorial composition, modern editorial branding, ";
        $base .= "structured high-contrast negative space, premium brand guidelines board. ";
        $base .= "Brand description: {$description}. ";

        if (!empty(array_filter($brandInfo['colors'] ?? []))) {
            $base .= "Brand colors: {$colors}. ";
        }

        $base .= "Include: top module with brand name logo, typography system panel, color palette swatches, ";
        $base .= "lifestyle photography strip with four images, logo adaptability panel. ";
        $base .= "Minimal swiss editorial layout, high-end branding, clean bento UI containers, ";
        $base .= "crisp drop shadows, rounded modules, structured spacing, premium print brand board.";

        return $base;
    }
}
