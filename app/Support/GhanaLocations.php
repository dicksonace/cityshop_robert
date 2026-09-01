<?php

namespace App\Support;

class GhanaLocations
{
    public const OTHER_CITY = 'Other city';

    /**
     * Display order for region pickers (Greater Accra first).
     *
     * @return list<string>
     */
    public static function regionOrder(): array
    {
        return [
            'Greater Accra',
            'Ashanti',
            'Western',
            'Eastern',
            'Central',
            'Northern',
            'Upper East',
            'Upper West',
            'Volta',
            'Bono',
            'Western North',
            'Ahafo',
            'Bono East',
            'North East',
            'Savannah',
            'Oti',
        ];
    }

    /**
     * @return list<string>
     */
    public static function regions(): array
    {
        $keys = array_keys(self::citiesByRegion());
        $ordered = array_values(array_intersect(self::regionOrder(), $keys));

        foreach ($keys as $key) {
            if (! in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        return $ordered;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function citiesByRegion(): array
    {
        return [
            'Greater Accra' => [
                'Abeka', 'Ablekuma', 'Accra', 'Accra Central', 'Achimota', 'Adabraka', 'Adenta', 'Airport',
                'Amasaman', 'Ashaiman', 'Atomic', 'Avenor', 'Awoshie', 'Baatsona', 'Bubuashie', 'Cantonments',
                'Chorkor', 'Circle', 'Dansoman', 'Darkuman', 'Dawhenya', 'Dodowa', 'Dome', 'Dzorwulu',
                'East Legon', 'Gbawe', 'Haatso', 'Jamestown', 'Kanda', 'Kaneshie', 'Kasoa', 'Kokomlemle',
                'Korle Bu', 'Kwashieman', 'Labadi', 'Labone', 'Lapaz', 'Lashibi', 'Legon', 'Madina', 'Makola',
                'Mallam', 'Mamprobi', 'Mataheko', 'North Industrial Area', 'North Kaneshie', 'Nungua', 'Ofankor',
                'Osu', 'Oyarifa', 'Pokuase', 'Prampram', 'Ridge', 'Roman Ridge', 'Sakumono', 'Santa Maria',
                'South Industrial Area', 'Spintex', 'Spintex Road', 'Tema', 'Teshie', 'Teshie Nungua', 'Tesano',
                'Trasaco', 'Tudu', 'Usher Town', 'Weija', self::OTHER_CITY,
            ],
            'Ashanti' => ['Kumasi', 'Obuasi', 'Ejisu', 'Konongo', 'Mampong', 'Bekwai', 'Offinso', self::OTHER_CITY],
            'Western' => ['Takoradi', 'Sekondi', 'Tarkwa', 'Axim', self::OTHER_CITY],
            'Eastern' => ['Koforidua', 'Nkawkaw', 'Akosombo', 'Nsawam', 'Suhum', 'Akim Oda', self::OTHER_CITY],
            'Central' => ['Cape Coast', 'Kasoa', 'Winneba', 'Elmina', 'Mankessim', 'Swedru', self::OTHER_CITY],
            'Northern' => ['Tamale', 'Yendi', 'Savelugu', self::OTHER_CITY],
            'Upper East' => ['Bolgatanga', 'Bawku', 'Navrongo', self::OTHER_CITY],
            'Upper West' => ['Wa', 'Lawra', 'Nandom', 'Jirapa', self::OTHER_CITY],
            'Volta' => ['Ho', 'Hohoe', 'Keta', 'Aflao', 'Kpandu', self::OTHER_CITY],
            'Bono' => ['Sunyani', 'Berekum', 'Dormaa Ahenkro', 'Wenchi', self::OTHER_CITY],
            'Western North' => ['Sefwi Wiawso', 'Bibiani', 'Sefwi Bekwai', 'Enchi', 'Juaboso', self::OTHER_CITY],
            'Ahafo' => ['Goaso', 'Bechem', 'Duayaw Nkwanta', 'Kukuom', 'Hwidiem', self::OTHER_CITY],
            'Bono East' => ['Techiman', 'Kintampo', 'Nkoranza', 'Atebubu', self::OTHER_CITY],
            'North East' => [
                'Bunkpurugu', 'Chereponi', 'Demon', 'Gambaga', 'Jimbale', 'Nakpanduri',
                'Nalerigu', 'Walewale', 'Wenchiki', 'Yunyoo', self::OTHER_CITY,
            ],
            'Savannah' => [
                'Bole', 'Buipe', 'Canteen', 'Daboya', 'Damongo', 'Gbintiri', 'Grupe', 'Kalande',
                'Lungbunga', 'Salaga', 'Sawla', 'Tuna', 'Yapei', self::OTHER_CITY,
            ],
            'Oti' => [
                'Akpafu', 'Brewaniase', 'Chinderi', 'Dambai', 'Jasikan', 'Kate krachi', 'Kpassa',
                'Krachi Nchumuru', 'Kwamekrom', 'Likpe', 'Lolobi', 'Nkwanta', 'Santrokofi',
                'Worawora', self::OTHER_CITY,
            ],
        ];
    }

    public static function isValidRegion(string $region): bool
    {
        return in_array($region, self::regions(), true);
    }

    public static function isValidCity(string $region, string $city): bool
    {
        $cities = self::citiesByRegion()[$region] ?? null;

        if (! is_array($cities)) {
            return false;
        }

        if (in_array($city, $cities, true)) {
            return true;
        }

        return $city !== '' && $city !== self::OTHER_CITY;
    }
}