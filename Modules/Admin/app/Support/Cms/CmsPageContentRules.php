<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use Modules\Admin\Support\Enums\CmsPageKey;

final class CmsPageContentRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(CmsPageKey $key): array
    {
        return match ($key) {
            CmsPageKey::Home => self::home(),
            CmsPageKey::Features => self::features(),
            CmsPageKey::About => self::about(),
            CmsPageKey::Contact => self::contact(),
            CmsPageKey::Terms, CmsPageKey::Privacy => self::legal(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function home(): array
    {
        return [
            'content.hero.badge' => ['required', 'string', 'max:120'],
            'content.hero.title' => ['required', 'string', 'max:255'],
            'content.hero.title_highlight' => ['required', 'string', 'max:120'],
            'content.hero.subtitle' => ['required', 'string', 'max:1000'],
            'content.hero.primary_cta_label' => ['required', 'string', 'max:64'],
            'content.hero.secondary_cta_label' => ['required', 'string', 'max:64'],
            ...self::image('content.hero'),
            'content.stats.items' => ['required', 'array', 'size:3'],
            'content.stats.items.*.value' => ['required', 'string', 'max:64'],
            'content.stats.items.*.label' => ['required', 'string', 'max:255'],
            'content.values.items' => ['required', 'array', 'size:3'],
            'content.values.items.*.title' => ['required', 'string', 'max:255'],
            'content.values.items.*.description' => ['required', 'string', 'max:1000'],
            'content.feature_blocks.items' => ['required', 'array', 'size:4'],
            'content.feature_blocks.items.*.eyebrow' => ['required', 'string', 'max:120'],
            'content.feature_blocks.items.*.title' => ['required', 'string', 'max:255'],
            'content.feature_blocks.items.*.body' => ['required', 'string', 'max:2000'],
            ...self::image('content.feature_blocks.items.*'),
            'content.feature_blocks.items.0.bullets' => ['required', 'array', 'size:2'],
            'content.feature_blocks.items.0.bullets.*' => ['required', 'string', 'max:255'],
            'content.feature_blocks.items.1.mini_cards' => ['required', 'array', 'size:2'],
            'content.feature_blocks.items.1.mini_cards.*.title' => ['required', 'string', 'max:120'],
            'content.feature_blocks.items.1.mini_cards.*.description' => ['required', 'string', 'max:500'],
            'content.feature_blocks.items.2.chat_user' => ['required', 'string', 'max:500'],
            'content.feature_blocks.items.2.chat_ai' => ['required', 'string', 'max:1000'],
            'content.feature_blocks.items.3.metrics' => ['required', 'array', 'size:2'],
            'content.feature_blocks.items.3.metrics.*.value' => ['required', 'string', 'max:64'],
            'content.feature_blocks.items.3.metrics.*.label' => ['required', 'string', 'max:120'],
            'content.testimonials.heading' => ['required', 'string', 'max:255'],
            'content.testimonials.subtitle' => ['required', 'string', 'max:500'],
            'content.testimonials.items' => ['required', 'array', 'size:3'],
            'content.testimonials.items.*.name' => ['required', 'string', 'max:120'],
            'content.testimonials.items.*.role' => ['required', 'string', 'max:255'],
            'content.testimonials.items.*.quote' => ['required', 'string', 'max:1000'],
            ...self::image('content.testimonials.items.*', alt: false),
            'content.pricing.heading' => ['required', 'string', 'max:255'],
            'content.pricing.subtitle' => ['required', 'string', 'max:500'],
            'content.pricing.free.name' => ['required', 'string', 'max:120'],
            'content.pricing.free.description' => ['required', 'string', 'max:255'],
            'content.pricing.free.cta_label' => ['required', 'string', 'max:64'],
            'content.pricing.free.features_included' => ['required', 'array', 'size:2'],
            'content.pricing.free.features_included.*' => ['required', 'string', 'max:255'],
            'content.pricing.free.features_excluded' => ['required', 'array', 'size:2'],
            'content.pricing.free.features_excluded.*' => ['required', 'string', 'max:255'],
            'content.pricing.premium_yearly.description' => ['required', 'string', 'max:255'],
            'content.pricing.premium_yearly.cta_label_prefix' => ['required', 'string', 'max:64'],
            'content.pricing.premium_yearly.features' => ['required', 'array', 'size:5'],
            'content.pricing.premium_yearly.features.*' => ['required', 'string', 'max:255'],
            'content.pricing.premium_monthly.name' => ['required', 'string', 'max:120'],
            'content.pricing.premium_monthly.description' => ['required', 'string', 'max:255'],
            'content.pricing.premium_monthly.note' => ['required', 'string', 'max:500'],
            'content.pricing.premium_monthly.cta_label' => ['required', 'string', 'max:64'],
            'content.pricing.premium_monthly.features' => ['required', 'array', 'size:4'],
            'content.pricing.premium_monthly.features.*' => ['required', 'string', 'max:255'],
            'content.pricing.detail_link_label' => ['required', 'string', 'max:120'],
            'content.faq.heading' => ['required', 'string', 'max:255'],
            'content.faq.items' => ['required', 'array', 'size:5'],
            'content.faq.items.*.question' => ['required', 'string', 'max:500'],
            'content.faq.items.*.answer' => ['required', 'string', 'max:2000'],
            'content.cta.title' => ['required', 'string', 'max:255'],
            'content.cta.subtitle' => ['required', 'string', 'max:500'],
            'content.cta.primary_label' => ['required', 'string', 'max:64'],
            'content.cta.secondary_label' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function features(): array
    {
        return [
            'content.hero.title' => ['required', 'string', 'max:255'],
            'content.hero.subtitle' => ['required', 'string', 'max:1000'],
            'content.hero.primary_cta_label' => ['required', 'string', 'max:64'],
            'content.hero.secondary_cta_label' => ['required', 'string', 'max:64'],
            'content.hero.video_url' => ['required', 'string', 'max:2048'],
            'content.bento.qbank.title' => ['required', 'string', 'max:255'],
            'content.bento.qbank.body' => ['required', 'string', 'max:2000'],
            'content.bento.qbank.tags' => ['required', 'array', 'size:3'],
            'content.bento.qbank.tags.*' => ['required', 'string', 'max:64'],
            ...self::image('content.bento.qbank'),
            'content.bento.study_exam.title' => ['required', 'string', 'max:255'],
            'content.bento.study_exam.body' => ['required', 'string', 'max:2000'],
            'content.bento.flashcards.title' => ['required', 'string', 'max:255'],
            'content.bento.flashcards.body' => ['required', 'string', 'max:2000'],
            'content.bento.ai_tutor.title' => ['required', 'string', 'max:255'],
            'content.bento.ai_tutor.body' => ['required', 'string', 'max:2000'],
            'content.bento.ai_tutor.cta_label' => ['required', 'string', 'max:64'],
            ...self::image('content.bento.ai_tutor'),
            'content.bento.analytics.title' => ['required', 'string', 'max:255'],
            'content.bento.analytics.badge' => ['required', 'string', 'max:64'],
            'content.bento.analytics.body' => ['required', 'string', 'max:2000'],
            ...self::image('content.bento.analytics'),
            'content.bento.library.title' => ['required', 'string', 'max:255'],
            'content.bento.library.body' => ['required', 'string', 'max:2000'],
            ...self::image('content.bento.library'),
            'content.bento.path.title' => ['required', 'string', 'max:255'],
            'content.bento.path.body' => ['required', 'string', 'max:2000'],
            'content.bento.exam_sim.title' => ['required', 'string', 'max:255'],
            'content.bento.exam_sim.body' => ['required', 'string', 'max:2000'],
            'content.bento.exam_sim.stat_value' => ['required', 'string', 'max:32'],
            'content.bento.exam_sim.stat_label' => ['required', 'string', 'max:64'],
            'content.cta.title' => ['required', 'string', 'max:255'],
            'content.cta.subtitle' => ['required', 'string', 'max:500'],
            'content.cta.primary_label' => ['required', 'string', 'max:64'],
            'content.cta.footnote' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function about(): array
    {
        return [
            'content.hero.title' => ['required', 'string', 'max:255'],
            'content.hero.subtitle' => ['required', 'string', 'max:500'],
            'content.story.heading' => ['required', 'string', 'max:255'],
            'content.story.paragraph_1' => ['required', 'string', 'max:5000'],
            'content.story.paragraph_2' => ['required', 'string', 'max:5000'],
            'content.story.tagline' => ['required', 'string', 'max:255'],
            ...self::image('content.story'),
            'content.values.heading' => ['required', 'string', 'max:255'],
            'content.values.items' => ['required', 'array', 'size:4'],
            'content.values.items.*.title' => ['required', 'string', 'max:255'],
            'content.values.items.*.description' => ['required', 'string', 'max:1000'],
            'content.stats.items' => ['required', 'array', 'size:4'],
            'content.stats.items.*.value' => ['required', 'string', 'max:64'],
            'content.stats.items.*.label' => ['required', 'string', 'max:255'],
            'content.experts.heading' => ['required', 'string', 'max:255'],
            'content.experts.subtitle' => ['required', 'string', 'max:500'],
            'content.experts.items' => ['required', 'array', 'size:6'],
            'content.experts.items.*.name' => ['required', 'string', 'max:255'],
            'content.experts.items.*.role' => ['required', 'string', 'max:255'],
            ...self::image('content.experts.items.*', alt: false),
            'content.partners.label' => ['required', 'string', 'max:255'],
            'content.partners.items' => ['required', 'array', 'size:5'],
            'content.partners.items.*' => ['required', 'string', 'max:255'],
            'content.cta.title' => ['required', 'string', 'max:255'],
            'content.cta.subtitle' => ['required', 'string', 'max:500'],
            'content.cta.primary_label' => ['required', 'string', 'max:64'],
            'content.cta.secondary_label' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function contact(): array
    {
        return [
            'content.intro.title' => ['required', 'string', 'max:255'],
            'content.intro.text' => ['required', 'string', 'max:2000'],
            'content.email' => ['required', 'email', 'max:255'],
            'content.hotline' => ['required', 'string', 'max:64'],
            'content.address' => ['required', 'string', 'max:500'],
            'content.hours' => ['required', 'string', 'max:255'],
            ...self::image('content.map'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function legal(): array
    {
        return [
            'content.intro' => ['required', 'string', 'max:2000'],
            'content.sections' => ['required', 'array', 'min:1', 'max:20'],
            'content.sections.*.title' => ['required', 'string', 'max:255'],
            'content.sections.*.body' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function image(string $prefix, bool $alt = true): array
    {
        $rules = [
            "{$prefix}.image_media_id" => ['nullable', 'integer', 'exists:media,id'],
            "{$prefix}.image_url" => ['required_without:'.$prefix.'.image_media_id', 'nullable', 'string', 'max:2048'],
        ];

        if ($alt) {
            $rules["{$prefix}.image_alt"] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }
}
