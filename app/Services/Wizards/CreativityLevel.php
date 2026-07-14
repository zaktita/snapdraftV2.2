<?php

namespace App\Services\Wizards;

use App\Models\Project;

class CreativityLevel
{
    public const STRICT = 'strict';

    public const BALANCED = 'balanced';

    public const CREATIVE = 'creative';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::STRICT, self::BALANCED, self::CREATIVE];
    }

    public static function normalize(?string $level): string
    {
        $level = strtolower(trim((string) $level));

        return in_array($level, self::values(), true) ? $level : self::BALANCED;
    }

    public static function fromProject(Project $project): string
    {
        return self::normalize(data_get($project->settings, 'creativity_level'));
    }

    public static function isPromptForgeLab(Project $project): bool
    {
        return ($project->settings['wizard_type'] ?? null) === 'prompt_forge_lab';
    }

    /**
     * CSV / Cluster CSV / PromptForge all use master-prompt generation.
     */
    public static function usesMasterPromptPipeline(Project $project): bool
    {
        return in_array(
            $project->settings['wizard_type'] ?? null,
            ['csv', 'csv_cluster', 'prompt_forge_lab'],
            true,
        );
    }

    public static function step2Instruction(string $level): string
    {
        return match (self::normalize($level)) {
            self::STRICT => 'Creativity level: STRICT. Replicate the attached reference template exactly — same layout skeleton, structural elements (grids, cutouts, masks, 3D elements, badges, text zones if present), color blocks, logo treatment, and composition. Swap only the imagery subject and on-image copy — the new subject comes from the caption, never from what the references depict. Do not invent a new layout or visual form.',
            self::CREATIVE => 'Creativity level: CREATIVE. Caption intent leads the visual form. Stay on-brand (palette, typography, voice, motifs from DNA) but you may reinterpret layout, composition, and structural elements. References supply brand style only — never their imagery subjects or copy.',
            default => 'Creativity level: BALANCED. Follow the reference cluster layout family closely — same structural elements and visual rhythm — with minor tasteful variation allowed in photo treatment or secondary elements. Blend caption intent with reference layout. The imagery subject comes from the caption, never from what the references depict.',
        };
    }

    public static function step2FactRetentionBlock(): string
    {
        return 'Fact retention: Extract the concrete facts the caption actually contains (e.g. names, lists/categories, dates, locations, people, prices/offers, qualifications, audience, deadlines, CTA — only those that are present). '
            .'When the visual carries text, carry the facts that the reference layout has room for into the matching on_image_text zones, so the visual communicates the real message rather than vague filler. '
            .'When the visual is text-free, carry facts into post.concept and the visual subject/composition instead. '
            .'Do not fabricate facts that are not in the caption, and do not replace real facts with generic placeholder copy (in any language) when concrete details exist. '
            .'If the caption lacks the fact a reference zone would carry, OMIT that zone entirely — never pad it with invented or filler copy (e.g. "coming soon", "join us", "more info"). A sparse caption must produce a sparse visual.';
    }

    public static function step2OnImageTextBlock(): string
    {
        return 'On-image text (conditional — only when the visual carries text): First check cluster text_density. If text-free, set on_image_text to []. '
            .'Otherwise, reproduce the same kind of text zones the attached references actually use — but only the zones the caption can actually fill. '
            .'If those references are minimal (e.g. a single statement or logo only), stay minimal; if they use a headline, subhead, bulleted/category list, pills/badges, or a CTA, reproduce those zones when the caption provides the matching facts. '
            .'When the references show a list and the caption provides list-like items, include a list zone (role "list", items separated by " • " or as an array). Use the cluster metadata (text_placement, layout_skeleton, graphic_devices, text_density) as the guide. '
            .'If the caption lacks a date, venue, list, or other fact a reference zone carries, omit that zone rather than inventing text for it. '
            .'Keep each zone scannable rather than a full paragraph. Spell every string exactly as it must render.';
    }

    public static function step2SubjectBlock(): string
    {
        return 'Imagery subject (mandatory when the visual includes photos or illustrations): The references define template and style ONLY — their people, objects, and scenes must be replaced. '
            .'Set post.subject to a concrete description of what the imagery must depict, derived from the caption topic — not from what the reference images show. '
            .'Describe the same new subject in post.concept, and add an entry to quality.avoid blocking reuse of the reference images\' depicted content.';
    }

    public static function referenceUsageForCluster(string $level, string $clusterLabel, int $imageCount): string
    {
        $base = 'Use all '.$imageCount.' attached reference images as the complete cluster template set for "'.$clusterLabel.'". ';

        return match (self::normalize($level)) {
            self::STRICT => $base.'Replicate their layout skeleton, graphic devices, palette, typography, background treatment, composition, photo treatment, and structural elements exactly — swap only subject and copy. Do not reuse the people, objects, or scenes the references depict.',
            self::CREATIVE => $base.'Match brand palette, typography, rendering style, and visual voice; layout and structural elements may differ from references while staying on-brand. Do not reuse the people, objects, or scenes the references depict.',
            default => $base.'Match layout skeleton, graphic devices, palette, typography, background treatment, composition, and structural elements from this cluster family with only minor variation. Do not reuse the people, objects, or scenes the references depict.',
        };
    }

    public static function imageReferenceInstruction(string $level, int $referenceImageCount): string
    {
        $label = $referenceImageCount === 1 ? 'image' : 'images';

        return match (self::normalize($level)) {
            self::STRICT => sprintf(
                'The %d attached reference %s define the visual template. Replicate their layout skeleton, structural elements (grids, cutouts, masks, 3D elements, badges, text zones if present), typography style, color-block structure, photo treatment, spacing, and logo treatment EXACTLY. Swap only subject and on-image copy.',
                $referenceImageCount,
                $label,
            ),
            self::CREATIVE => sprintf(
                'The %d attached reference %s show the brand visual language. Use them for palette, typography style, rendering style, and on-brand mood. Layout, composition, and structural elements may differ; do not rigidly copy the reference layout.',
                $referenceImageCount,
                $label,
            ),
            default => sprintf(
                'The %d attached reference %s define the visual template. Closely replicate their layout skeleton, structural elements (grids, cutouts, masks, 3D elements, badges, text zones if present), typography style, color-block structure, photo treatment, spacing, and logo treatment. Minor tasteful variation is allowed; do not invent a new layout system.',
                $referenceImageCount,
                $label,
            ),
        };
    }
}
