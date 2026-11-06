<?php
namespace App\Support;

/** Editable site copy, grouped for the admin settings screen. type: text | textarea */
final class SettingsSchema
{
    public static function groups(): array
    {
        $sec = fn(string $p) => [
            "{$p}_title" => ['Heading', 'text'],
            "{$p}_lede" => ['Intro text', 'textarea'],
        ];
        return [
            'General' => [
                'site_title' => ['Site / page title', 'text'],
                'footer_copyright' => ['Footer copyright line', 'text'],
            ],
            'Hero' => [
                'hero_title' => ['Heading', 'text'],
                'hero_accent' => ['Heading, second half (optional, same style)', 'text'],
                'hero_scope' => ['Scope line under the heading (also read by search engines)', 'text'],
                'hero_lede' => ['Intro text', 'textarea'],
                'hero_check1' => ['Check item 1', 'text'],
                'hero_check2' => ['Check item 2', 'text'],
                'hero_check3' => ['Check item 3', 'text'],
            ],
            'Industries section' => $sec('industries'),
            'Across every industry' => [
                'caps_title' => ['Heading', 'text'],
                'caps_lede' => ['Intro text', 'textarea'],
                'twin_title' => ['Digital twin: name', 'text'],
                'twin_body' => ['Digital twin: what it is', 'textarea'],
                'twin_outcome' => ['Digital twin: what changes', 'textarea'],
                'dt_title' => ['Digital transformation: name', 'text'],
                'dt_body' => ['Digital transformation: what it is', 'textarea'],
                'dt_outcome' => ['Digital transformation: what changes', 'textarea'],
            ],
            'AI services section' => [
                'ai_title' => ['Heading', 'text'],
                'ai_lede' => ['Intro text', 'textarea'],
            ],
            'How we help section' => $sec('process'),
            'About section' => $sec('about'),
            'Search engines (SEO)' => [
                'seo_title' => ['Home page title (about 60 characters)', 'text'],
                'seo_description' => ['Home page description (up to 160 characters)', 'textarea'],
            ],
            'Contact section' => [
                'contact_title' => ['Heading', 'text'],
                'contact_lede' => ['Intro text', 'textarea'],
                'contact_email' => ['Email', 'text'],
                'contact_phone' => ['Phone', 'text'],
                'contact_office' => ['Office', 'text'],
                'contact_website' => ['Website', 'text'],
            ],
        ];
    }

    public static function keys(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::groups())));
    }
}
