<?php

namespace App\Support;

class GhanaLocations
{
    /**
     * @return list<string>
     */
    public static function regions(): array
    {
        return array_keys(self::citiesByRegion());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function citiesByRegion(): array
    {
        return [
            'Ahafo' => ['Goaso', 'Bechem', 'Duayaw Nkwanta', 'Kukuom', 'Hwidiem'],
            'Ashanti' => ['Kumasi', 'Obuasi', 'Ejisu', 'Konongo', 'Mampong', 'Bekwai', 'Offinso'],
            'Bono' => ['Sunyani', 'Berekum', 'Dormaa Ahenkro', 'Wenchi'],
            'Bono East' => ['Techiman', 'Kintampo', 'Nkoranza', 'Atebubu'],
            'Central' => ['Cape Coast', 'Kasoa', 'Winneba', 'Elmina', 'Mankessim', 'Swedru'],
            'Eastern' => ['Koforidua', 'Nkawkaw', 'Akosombo', 'Nsawam', 'Suhum', 'Akim Oda'],
            'Greater Accra' => [
                'Abeka', 'Ablekuma', 'Accra', 'Accra Central', 'Achimota', 'Adabraka', 'Adenta', 'Airport',
                'Amasaman', 'Ashaiman', 'Atomic', 'Avenor', 'Awoshie', 'Baatsona', 'Bubuashie', 'Cantonments',
                'Chorkor', 'Circle', 'Dansoman', 'Darkuman', 'Dawhenya', 'Dodowa', 'Dome', 'Dzorwulu',
                'East Legon', 'Gbawe', 'Haatso', 'Jamestown', 'Kanda', 'Kaneshie', 'Kasoa', 'Kokomlemle',
                'Korle Bu', 'Kwashieman', 'Labadi', 'Labone', 'Lapaz', 'Lashibi', 'Legon', 'Madina', 'Makola',
                'Mallam', 'Mamprobi', 'Mataheko', 'North Industrial Area', 'North Kaneshie', 'Nungua', 'Ofankor',
                'Osu', 'Oyarifa', 'Pokuase', 'Prampram', 'Ridge', 'Roman Ridge', 'Sakumono', 'Santa Maria',
                'South Industrial Area', 'Spintex', 'Spintex Road', 'Tema', 'Teshie', 'Teshie Nungua', 'Tesano',
                'Trasaco', 'Tudu', 'Usher Town', 'Weija', 'Other city',
            ],
            'North East' => [
                'Bunkpurugu', 'Chereponi', 'Demon', 'Gambaga', 'Jimbale', 'Nakpanduri',
                'Nalerigu', 'Walewale', 'Wenchiki', 'Yunyoo', 'Other city',
            ],
            'Northern' => ['Tamale', 'Yendi', 'Savelugu'],
            'Oti' => [
                'Akpafu', 'Brewaniase', 'Chinderi', 'Dambai', 'Jasikan', 'Kate krachi', 'Kpassa',
                'Krachi Nchumuru', 'Kwamekrom', 'Likpe', 'Lolobi', 'Nkwanta', 'Santrokofi',
                'Worawora', 'Other city',
            ],
            'Savannah' => [
                'Bole', 'Buipe', 'Canteen', 'Daboya', 'Damongo', 'Gbintiri', 'Grupe', 'Kalande',
                'Lungbunga', 'Salaga', 'Sawla', 'Tuna', 'Yapei', 'Other city',
            ],
            'Upper East' => ['Bolgatanga', 'Bawku', 'Navrongo'],
            'Upper West' => ['Wa', 'Lawra', 'Nandom', 'Jirapa'],
            'Volta' => ['Ho', 'Hohoe', 'Keta', 'Aflao', 'Kpandu'],
            'Western' => ['Takoradi', 'Sekondi', 'Tarkwa', 'Axim'],
            'Western North' => ['Sefwi Wiawso', 'Bibiani', 'Sefwi Bekwai', 'Enchi', 'Juaboso'],
        ];
    }

    public static function isValidRegion(string $region): bool
    {
        return in_array($region, self::regions(), true);
    }

    public static function isValidCity(string $region, string $city): bool
    {
        $cities = self::citiesByRegion()[$region] ?? null;

        return is_array($cities) && in_array($city, $cities, true);
    }
}
