<?php

namespace App\Support;

class Countries
{
    /**
     * Display names accepted at registration. Ghana first (home market).
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            'Ghana',
            'Nigeria',
            'Togo',
            'Benin',
            'Côte d\'Ivoire',
            'Burkina Faso',
            'Mali',
            'Senegal',
            'Liberia',
            'Sierra Leone',
            'Guinea',
            'Gambia',
            'Cameroon',
            'Kenya',
            'Uganda',
            'Tanzania',
            'Rwanda',
            'South Africa',
            'Egypt',
            'Morocco',
            'Algeria',
            'Tunisia',
            'Ethiopia',
            'Zimbabwe',
            'Zambia',
            'Botswana',
            'Namibia',
            'Mozambique',
            'Angola',
            'Democratic Republic of the Congo',
            'United Kingdom',
            'United States',
            'Canada',
            'Germany',
            'France',
            'Netherlands',
            'Italy',
            'Spain',
            'Portugal',
            'Ireland',
            'United Arab Emirates',
            'Saudi Arabia',
            'Qatar',
            'India',
            'China',
            'Australia',
        ];
    }

    public static function default(): string
    {
        return 'Ghana';
    }

    public static function isValid(?string $name): bool
    {
        return is_string($name) && in_array($name, self::names(), true);
    }
}
