<?php

return [

    'computers' => [
        'icon' => '💻',
        'fields' => [
            ['key' => 'processor', 'label' => 'Processor', 'type' => 'text'],
            ['key' => 'ram', 'label' => 'RAM', 'type' => 'select', 'options' => ['4GB', '8GB', '16GB', '32GB', '64GB']],
            ['key' => 'storage', 'label' => 'Storage', 'type' => 'select', 'options' => ['128GB SSD', '256GB SSD', '512GB SSD', '1TB SSD', '1TB HDD']],
            ['key' => 'display', 'label' => 'Display', 'type' => 'text'],
            ['key' => 'graphics', 'label' => 'Graphics', 'type' => 'text'],
            ['key' => 'os', 'label' => 'Operating System', 'type' => 'select', 'options' => ['Windows 11', 'macOS', 'Linux', 'Chrome OS']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Refurbished', 'Used - Like New', 'Used - Good']],
        ],
    ],

    'phones-tablets' => [
        'icon' => '📱',
        'fields' => [
            ['key' => 'screen_size', 'label' => 'Screen Size', 'type' => 'text'],
            ['key' => 'storage', 'label' => 'Storage', 'type' => 'select', 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB']],
            ['key' => 'ram', 'label' => 'RAM', 'type' => 'select', 'options' => ['3GB', '4GB', '6GB', '8GB', '12GB', '16GB']],
            ['key' => 'battery', 'label' => 'Battery', 'type' => 'text'],
            ['key' => 'camera', 'label' => 'Camera', 'type' => 'text'],
            ['key' => 'network', 'label' => 'Network', 'type' => 'select', 'options' => ['4G LTE', '5G', 'Wi-Fi Only']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Refurbished', 'Used']],
        ],
    ],

    'electronics' => [
        'icon' => '⚡',
        'fields' => [
            ['key' => 'power', 'label' => 'Power / Wattage', 'type' => 'text'],
            ['key' => 'voltage', 'label' => 'Voltage', 'type' => 'select', 'options' => ['110V', '220V', 'Dual Voltage']],
            ['key' => 'connectivity', 'label' => 'Connectivity', 'type' => 'text'],
            ['key' => 'warranty', 'label' => 'Warranty', 'type' => 'select', 'options' => ['3 Months', '6 Months', '1 Year', '2 Years']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Refurbished', 'Used']],
        ],
    ],

    'fashion' => [
        'icon' => '👗',
        'fields' => [
            ['key' => 'size', 'label' => 'Size', 'type' => 'select', 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size']],
            ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['Men', 'Women', 'Unisex', 'Kids']],
            ['key' => 'care', 'label' => 'Care Instructions', 'type' => 'text'],
        ],
    ],

    'home-garden' => [
        'icon' => '🏠',
        'fields' => [
            ['key' => 'dimensions', 'label' => 'Dimensions', 'type' => 'text'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'weight', 'label' => 'Weight', 'type' => 'text'],
            ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
            ['key' => 'indoor_outdoor', 'label' => 'Usage', 'type' => 'select', 'options' => ['Indoor', 'Outdoor', 'Both']],
        ],
    ],

    'appliances' => [
        'icon' => '🔌',
        'fields' => [
            ['key' => 'capacity', 'label' => 'Capacity', 'type' => 'text'],
            ['key' => 'energy_rating', 'label' => 'Energy Rating', 'type' => 'select', 'options' => ['A+++', 'A++', 'A+', 'A', 'B']],
            ['key' => 'power', 'label' => 'Power Consumption', 'type' => 'text'],
            ['key' => 'warranty', 'label' => 'Warranty', 'type' => 'select', 'options' => ['6 Months', '1 Year', '2 Years', '3 Years']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Refurbished']],
        ],
    ],

    'beauty' => [
        'icon' => '💄',
        'fields' => [
            ['key' => 'volume', 'label' => 'Volume / Size', 'type' => 'text'],
            ['key' => 'skin_type', 'label' => 'Skin Type', 'type' => 'select', 'options' => ['All Skin Types', 'Oily', 'Dry', 'Combination', 'Sensitive']],
            ['key' => 'ingredients', 'label' => 'Key Ingredients', 'type' => 'text'],
            ['key' => 'expiry', 'label' => 'Shelf Life', 'type' => 'text'],
        ],
    ],

    'sports' => [
        'icon' => '⚽',
        'fields' => [
            ['key' => 'size', 'label' => 'Size', 'type' => 'text'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'sport_type', 'label' => 'Sport', 'type' => 'select', 'options' => ['Football', 'Basketball', 'Fitness', 'Running', 'Cycling', 'General']],
            ['key' => 'weight', 'label' => 'Weight', 'type' => 'text'],
        ],
    ],

    'vehicles' => [
        'icon' => '🚗',
        'fields' => [
            ['key' => 'vehicle_type', 'label' => 'Vehicle type', 'type' => 'select', 'options' => ['Car', 'Motorcycle', 'Truck', 'Van', 'Bus', 'Other']],
            ['key' => 'make', 'label' => 'Make', 'type' => 'text'],
            ['key' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['key' => 'year', 'label' => 'Year', 'type' => 'text'],
            ['key' => 'mileage', 'label' => 'Mileage / Odometer', 'type' => 'text'],
            ['key' => 'fuel_type', 'label' => 'Fuel type', 'type' => 'select', 'options' => ['Petrol', 'Diesel', 'Electric', 'Hybrid', 'LPG']],
            ['key' => 'transmission', 'label' => 'Transmission', 'type' => 'select', 'options' => ['Manual', 'Automatic']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Foreign Used', 'Locally Used', 'Refurbished']],
        ],
    ],

];
