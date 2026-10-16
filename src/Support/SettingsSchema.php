<?php
namespace App\Support;

/** Editable site copy, grouped for the admin settings screen. type: text | textarea */
final class SettingsSchema
{
    public static function groups(): array
    {
        $sec = fn(string $p) => [
            "{$p}_kicker" => ['Label', 'text'],
            "{$p}_title" => ['Heading', 'text'],
            "{$p}_lede" => ['Intro text', 'textarea'],
        ];
        return [
            'General' => [
                'site_title' => ['Site / page title', 'text'],
                'footer_copyright' => ['Footer copyright line', 'text'],
            ],
            'Hero' => [
                'hero_kicker' => ['Label', 'text'],
                'hero_title' => ['Heading (before the highlighted word)', 'text'],
                'hero_accent' => ['Highlighted word', 'text'],
                'hero_lede' => ['Intro text', 'textarea'],
                'hero_check1' => ['Check item 1', 'text'],
                'hero_check2' => ['Check item 2', 'text'],
                'hero_check3' => ['Check item 3', 'text'],
            ],
            'Services section' => $sec('services'),
            'Products section' => $sec('products'),
            'How we work section' => $sec('process'),
            'About section' => $sec('about'),
            'Industries section' => [
                'industries_kicker' => ['Label', 'text'],
                'industries_title' => ['Heading', 'text'],
                'industries_list' => ['Industries (one per line)', 'textarea'],
            ],
            'Contact section' => [
                'contact_kicker' => ['Label', 'text'],
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
