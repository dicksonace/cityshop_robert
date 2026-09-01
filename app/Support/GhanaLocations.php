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
            'Ashanti' => [
                'Abrepo', 'Abuakwa', 'Adum', 'Afrancho', 'Agogo', 'Agona', 'Ahafo Ano', 'Ahinsan', 'Ahodwo',
                'Airport Roundabout', 'Amakom', 'Asafo', 'Asante Manpong', 'Asokwa', 'Atronsu', 'Ayeduase', 'Ayigya',
                'Bantama', 'Bekwai', 'Bomfa', 'Adeduako', 'Effiduase', 'Ejisu', 'Emena', 'Fomena', 'Jacobu', 'Juaso',
                'Kentinkrono', 'Konongo', 'Kotei', 'Kumasi', 'Kwadaso', 'Manpong', 'Manso Nkwanta', 'Nhyiaeso', 'Nkawie',
                'Nsuta', 'Obuasi', 'Offinso', 'Pankrono', 'Tafo', 'Tanoso', 'Tech', 'Tepa', 'Toase', self::OTHER_CITY,
            ],
            'Western' => [
                'Agona Nkwanta', 'Ahanta West', 'Aiyinasi', 'Asankragwa', 'Axim', 'Bogoso', 'Bawdie', 'Daboase',
                'Effia', 'Ellembelle', 'Elubo', 'Enchi', 'Esiama', 'Essiama', 'Half Assini', 'Kojokrom',
                'Market Circle', 'Mpohor', 'Nsuaem', 'Nzema East', 'Prestea', 'Sekondi-Takoradi', 'Shama',
                'Takoradi Harbor', 'Tarkwa', 'Tarkwa Bremam', 'Wassa Akropong', self::OTHER_CITY,
            ],
            'Eastern' => [
                'Aburi', 'Achiase', 'Adukrom', 'Akim Oda', 'Akosombo', 'Akropong', 'Akwatia', 'Akyem Hemang',
                'Amanokrom', 'Asamankese', 'Atimpoku', 'Begoro', 'Coaltar', 'Effiduase', 'Kibi', 'Koforidua',
                'Kpong', 'Mamfe', 'Mpraeso', 'New Tafo', 'Nkawkaw', 'Nkurakan', 'Nsawam', 'Oda', 'Oyoko',
                'Somanya', 'Suhum', self::OTHER_CITY,
            ],
            'Central' => [
                'Abakrampa', 'Agona Swedru', 'Ajumako', 'Anomabo', 'Apam', 'Assin Fosu', 'Awutu Bereku',
                'Bremen Asikuma', 'Cape Coast', 'Dominase', 'Dunkwa-on-Offin', 'Elmina', 'Foso', 'Gomoa', 'Koaso',
                'Mankessim', 'Mumford', 'Nsuaem', 'Nyakrom', 'Saltpong', 'Twifo Praso', 'University of Cape Coast',
                'Winneba', 'Yamoransa', self::OTHER_CITY,
            ],
            'Northern' => ['Tamale', 'Yendi', 'Savelugu', self::OTHER_CITY],
            'Upper East' => [
                'Bawku', 'Binduri', 'Bolgatanga', 'Bongo', 'Garu', 'Navrongo', 'Paga', 'Pusiga', 'Pwalugu',
                'Telensi', 'Tempane', 'Tongo', 'Zebilla', 'Zuarungu', self::OTHER_CITY,
            ],
            'Upper West' => ['Wa', 'Lawra', 'Nandom', 'Jirapa', self::OTHER_CITY],
            'Volta' => ['Ho', 'Hohoe', 'Keta', 'Aflao', 'Kpandu', self::OTHER_CITY],
            'Bono' => ['Sunyani', 'Berekum', 'Dormaa Ahenkro', 'Wenchi', self::OTHER_CITY],
            'Western North' => [
                'Akontombra', 'Anhwinso', 'Asawinso', 'Bia', 'Bibiani', 'Bodi', 'Chirano', 'Dadieso', 'Debiso',
                'Enchi', 'Juaboso', 'Sefwi Awaso', 'Sefwi Bekwai', 'Sefwi Wiawso', self::OTHER_CITY,
            ],
            'Ahafo' => [
                'Acherensua', 'Bechem', 'Duayaw Nkwanta', 'Goaso', 'Hwidiem', 'Kenyase', 'Kukuom', 'Mim',
                'Noberkwa', 'Sankore', 'Yamfo', self::OTHER_CITY,
            ],
            'Bono East' => [
                'Abease', 'Amantin', 'Atebubu', 'Babatokuma', 'Jema', 'Kintampo', 'Kwame Danso', 'New Longoro',
                'Nkoranza', 'Nsuta', 'Prang', 'Techiman', 'Tuobodom', 'Yeji', self::OTHER_CITY,
            ],
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