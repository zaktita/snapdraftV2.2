<?php

namespace App\Services;

/**
 * Maps CSV / wizard format preset keys to Gemini image aspect ratios and prompt copy.
 *
 * @see https://ai.google.dev/gemini-api/docs/image-generation (supported aspect ratios)
 */
final class FormatPresetMapper
{
    /** @var array<string, array{aspectRatio: string, description: string}> */
    private const PRESETS = [
        // Legacy (kept for backwards compatibility)
        'square' => ['aspectRatio' => '1:1',  'description' => 'square (1:1)'],
        'portrait' => ['aspectRatio' => '9:16', 'description' => 'portrait (9:16)'],
        'landscape' => ['aspectRatio' => '16:9', 'description' => 'landscape (16:9)'],

        'instagram_square' => ['aspectRatio' => '1:1',  'description' => 'Instagram square feed (1:1)'],
        'instagram_portrait' => ['aspectRatio' => '4:5',  'description' => 'Instagram portrait feed (4:5)'],
        'instagram_story' => ['aspectRatio' => '9:16', 'description' => 'Instagram story / reel (9:16)'],
        'instagram_landscape' => ['aspectRatio' => '16:9', 'description' => 'Instagram landscape (16:9)'],

        'facebook_square' => ['aspectRatio' => '1:1',  'description' => 'Facebook square (1:1)'],
        'facebook_link' => ['aspectRatio' => '16:9', 'description' => 'Facebook link / OG-style wide card (≈16:9)'],
        'facebook_story' => ['aspectRatio' => '9:16', 'description' => 'Facebook story (9:16)'],
        'facebook_landscape' => ['aspectRatio' => '16:9', 'description' => 'Facebook landscape (16:9)'],

        'linkedin_square' => ['aspectRatio' => '1:1',  'description' => 'LinkedIn square (1:1)'],
        'linkedin_landscape' => ['aspectRatio' => '16:9', 'description' => 'LinkedIn wide post (16:9)'],

        'x_square' => ['aspectRatio' => '1:1',  'description' => 'X / Twitter square (1:1)'],
        'x_landscape' => ['aspectRatio' => '16:9', 'description' => 'X / Twitter landscape (16:9)'],

        'tiktok_video' => ['aspectRatio' => '9:16', 'description' => 'TikTok vertical video frame (9:16)'],
        'youtube_thumbnail' => ['aspectRatio' => '16:9', 'description' => 'YouTube thumbnail (16:9)'],
        'pinterest_pin' => ['aspectRatio' => '2:3',  'description' => 'Pinterest standard pin (2:3)'],
        'pinterest_square' => ['aspectRatio' => '1:1',  'description' => 'Pinterest square (1:1)'],

        // Reddit: distinct shapes for image posts vs link-style thumbnails in the feed
        'reddit_image_post' => ['aspectRatio' => '4:5',  'description' => 'Reddit in-feed image post - tall mobile card (4:5)'],
        'reddit_link_preview' => ['aspectRatio' => '16:9', 'description' => 'Reddit link / thumbnail preview - wide OG-style (16:9)'],
    ];

    public static function normalize(string $format): string
    {
        $key = strtolower(trim($format));

        if ($key === '') {
            return 'square';
        }

        return array_key_exists($key, self::PRESETS) ? $key : 'square';
    }

    /**
     * @return array{aspectRatio: string, description: string}
     */
    public static function resolve(string $format): array
    {
        $key = self::normalize($format);

        return self::PRESETS[$key];
    }

    public static function aspectRatio(string $format): string
    {
        $trimmed = trim($format);
        if (preg_match('/^\d+:\d+$/', $trimmed)) {
            return $trimmed;
        }

        return self::resolve($format)['aspectRatio'];
    }
}
