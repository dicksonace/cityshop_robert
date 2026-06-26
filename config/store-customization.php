<?php

return [

    'presets' => [
        'ocean' => [
            'label' => 'Ocean Blue',
            'primary_color' => '#1e3a8a',
            'secondary_color' => '#f97316',
            'background_color' => '#f8fafc',
            'text_color' => '#111827',
        ],
        'sunset' => [
            'label' => 'Sunset Glow',
            'primary_color' => '#9a3412',
            'secondary_color' => '#fbbf24',
            'background_color' => '#fff7ed',
            'text_color' => '#1c1917',
        ],
        'forest' => [
            'label' => 'Forest Green',
            'primary_color' => '#14532d',
            'secondary_color' => '#84cc16',
            'background_color' => '#f0fdf4',
            'text_color' => '#14532d',
        ],
        'midnight' => [
            'label' => 'Midnight',
            'primary_color' => '#0f172a',
            'secondary_color' => '#6366f1',
            'background_color' => '#f1f5f9',
            'text_color' => '#0f172a',
        ],
        'rose' => [
            'label' => 'Rose Boutique',
            'primary_color' => '#9d174d',
            'secondary_color' => '#fb7185',
            'background_color' => '#fff1f2',
            'text_color' => '#881337',
        ],
        'minimal' => [
            'label' => 'Clean Minimal',
            'primary_color' => '#374151',
            'secondary_color' => '#111827',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
        ],
    ],

    'defaults' => [
        'theme' => [
            'preset' => 'ocean',
            'primary_color' => '#1e3a8a',
            'secondary_color' => '#f97316',
            'background_color' => '#f8fafc',
            'text_color' => '#111827',
            'button_style' => 'rounded',
            'font_family' => 'system',
        ],
        'hero' => [
            'type' => 'static',
            'images' => [],
            'autoplay_seconds' => 5,
            'show_arrows' => true,
            'show_indicators' => true,
        ],
        'branding' => [
            'store_logo' => null,
            'cover_image' => null,
            'slogan' => '',
            'description' => '',
            'business_category' => '',
            'social_facebook' => '',
            'social_instagram' => '',
            'social_twitter' => '',
            'website' => '',
        ],
        'sections' => [
            'order' => ['announcement', 'hero', 'promo', 'featured', 'products', 'about', 'contact'],
            'enabled' => [
                'announcement' => false,
                'promo' => false,
                'featured' => true,
                'about' => true,
                'contact' => true,
            ],
        ],
        'product_display' => [
            'columns_mobile' => 2,
            'columns_tablet' => 3,
            'columns_desktop' => 4,
            'layout' => 'grid',
            'card_style' => 'shadow',
            'border_radius' => 'medium',
            'image_aspect' => 'square',
        ],
        'announcement' => [
            'enabled' => false,
            'text' => '',
            'background_color' => '#f97316',
            'text_color' => '#ffffff',
        ],
        'promo_banner' => [
            'enabled' => false,
            'text' => '',
            'background_color' => '#dc2626',
            'text_color' => '#ffffff',
            'image' => null,
            'starts_at' => null,
            'ends_at' => null,
        ],
    ],

];
