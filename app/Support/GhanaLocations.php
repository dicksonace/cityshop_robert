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
            'Greater Accra' => ['Accra', 'Tema', 'Madina', 'Kasoa', 'Ashaiman', 'Ablekuma', 'Adenta', 'Dodowa'],
            'North East' => ['Nalerigu', 'Walewale', 'Gambaga'],
            'Northern' => ['Tamale', 'Yendi', 'Savelugu'],
            'Oti' => ['Dambai', 'Jasikan', 'Kadjebi', 'Nkwanta'],
            'Savannah' => ['Damongo', 'Bole', 'Salaga'],
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
