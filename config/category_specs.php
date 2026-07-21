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
            ['key' => 'product_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Skincare', 'Makeup', 'Hair care', 'Fragrance', 'Personal care', 'Other']],
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

    'food-beverages' => [
        'icon' => '🍔',
        'fields' => [
            ['key' => 'product_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Food', 'Drink', 'Snack', 'Ingredient', 'Other']],
            ['key' => 'weight_volume', 'label' => 'Weight / Volume', 'type' => 'text'],
            ['key' => 'pack_size', 'label' => 'Pack size', 'type' => 'text'],
            ['key' => 'expiry', 'label' => 'Best before / Expiry', 'type' => 'text'],
            ['key' => 'storage', 'label' => 'Storage', 'type' => 'select', 'options' => ['Room temperature', 'Refrigerated', 'Frozen']],
            ['key' => 'dietary', 'label' => 'Dietary', 'type' => 'select', 'options' => ['None', 'Halal', 'Vegetarian', 'Vegan', 'Gluten-free']],
        ],
    ],

    'groceries' => [
        'icon' => '🛒',
        'fields' => [
            ['key' => 'product_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Staple', 'Household', 'Personal care', 'Snack', 'Other']],
            ['key' => 'weight_volume', 'label' => 'Weight / Volume', 'type' => 'text'],
            ['key' => 'pack_size', 'label' => 'Pack size', 'type' => 'text'],
            ['key' => 'expiry', 'label' => 'Best before / Expiry', 'type' => 'text'],
            ['key' => 'brand', 'label' => 'Brand', 'type' => 'text'],
        ],
    ],

    'health-pharmacy' => [
        'icon' => '💊',
        'fields' => [
            ['key' => 'product_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Medicine', 'Supplement', 'First aid', 'Medical device', 'Other']],
            ['key' => 'volume_size', 'label' => 'Size / Count', 'type' => 'text'],
            ['key' => 'expiry', 'label' => 'Expiry date', 'type' => 'text'],
            ['key' => 'prescription', 'label' => 'Prescription', 'type' => 'select', 'options' => ['Not required', 'Required']],
        ],
    ],

    'baby-kids' => [
        'icon' => '🍼',
        'fields' => [
            ['key' => 'age_range', 'label' => 'Age range', 'type' => 'select', 'options' => ['0-6 months', '6-12 months', '1-3 years', '3-5 years', '5-8 years', '8+ years']],
            ['key' => 'size', 'label' => 'Size', 'type' => 'text'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['Boys', 'Girls', 'Unisex']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Used - Like New', 'Used - Good']],
        ],
    ],

    'books-education' => [
        'icon' => '📚',
        'fields' => [
            ['key' => 'format', 'label' => 'Format', 'type' => 'select', 'options' => ['Paperback', 'Hardcover', 'Ebook', 'Workbook', 'Stationery set', 'Other']],
            ['key' => 'subject', 'label' => 'Subject / Topic', 'type' => 'text'],
            ['key' => 'level', 'label' => 'Level', 'type' => 'select', 'options' => ['Primary', 'JHS', 'SHS', 'Tertiary', 'Professional', 'General']],
            ['key' => 'language', 'label' => 'Language', 'type' => 'select', 'options' => ['English', 'Twi', 'French', 'Other']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Used - Like New', 'Used - Good']],
        ],
    ],

    'jewelry-watches' => [
        'icon' => '💍',
        'fields' => [
            ['key' => 'item_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Necklace', 'Bracelet', 'Earrings', 'Ring', 'Watch', 'Other']],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['Men', 'Women', 'Unisex', 'Kids']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Used - Like New', 'Used']],
        ],
    ],

    'bags-shoes' => [
        'icon' => '👟',
        'fields' => [
            ['key' => 'item_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Shoes', 'Sandals', 'Bags', 'Backpacks', 'Belts', 'Other']],
            ['key' => 'size', 'label' => 'Size', 'type' => 'text'],
            ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['Men', 'Women', 'Unisex', 'Kids']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Used - Like New', 'Used']],
        ],
    ],

    'tools-hardware' => [
        'icon' => '🔧',
        'fields' => [
            ['key' => 'tool_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Hand tool', 'Power tool', 'Hardware', 'Building material', 'Other']],
            ['key' => 'power_source', 'label' => 'Power source', 'type' => 'select', 'options' => ['Manual', 'Electric', 'Battery', 'Petrol', 'N/A']],
            ['key' => 'warranty', 'label' => 'Warranty', 'type' => 'select', 'options' => ['None', '3 Months', '6 Months', '1 Year']],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Used - Like New', 'Used']],
        ],
    ],

    'toys-games' => [
        'icon' => '🧸',
        'fields' => [
            ['key' => 'age_range', 'label' => 'Age range', 'type' => 'text'],
            ['key' => 'toy_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Educational', 'Outdoor', 'Board game', 'Electronic', 'Dolls & figures', 'Other']],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Used - Like New', 'Used']],
        ],
    ],

    'pet-supplies' => [
        'icon' => '🐾',
        'fields' => [
            ['key' => 'pet_type', 'label' => 'Pet', 'type' => 'select', 'options' => ['Dog', 'Cat', 'Bird', 'Fish', 'Other']],
            ['key' => 'product_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Food', 'Toy', 'Accessory', 'Health', 'Cage / Housing', 'Other']],
            ['key' => 'weight_size', 'label' => 'Weight / Size', 'type' => 'text'],
        ],
    ],

    'office-stationery' => [
        'icon' => '📎',
        'fields' => [
            ['key' => 'item_type', 'label' => 'Type', 'type' => 'select', 'options' => ['Stationery', 'Printer supplies', 'Desk accessory', 'Filing', 'Other']],
            ['key' => 'pack_size', 'label' => 'Pack size', 'type' => 'text'],
            ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
        ],
    ],

    'auto-parts' => [
        'icon' => '⚙️',
        'fields' => [
            ['key' => 'part_type', 'label' => 'Part type', 'type' => 'text'],
            ['key' => 'vehicle_make', 'label' => 'Fits make', 'type' => 'text'],
            ['key' => 'vehicle_model', 'label' => 'Fits model', 'type' => 'text'],
            ['key' => 'year_range', 'label' => 'Year range', 'type' => 'text'],
            ['key' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => ['Brand New', 'Used - Like New', 'Used', 'Refurbished']],
        ],
    ],

];
