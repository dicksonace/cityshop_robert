/** Ghana delivery locations — keep in sync with App\Support\GhanaLocations. */

export const GHANA_CITIES_BY_REGION: Record<string, string[]> = {
    Ahafo: ['Goaso', 'Bechem', 'Duayaw Nkwanta', 'Kukuom', 'Hwidiem'],
    Ashanti: ['Kumasi', 'Obuasi', 'Ejisu', 'Konongo', 'Mampong', 'Bekwai', 'Offinso'],
    Bono: ['Sunyani', 'Berekum', 'Dormaa Ahenkro', 'Wenchi'],
    'Bono East': ['Techiman', 'Kintampo', 'Nkoranza', 'Atebubu'],
    Central: ['Cape Coast', 'Kasoa', 'Winneba', 'Elmina', 'Mankessim', 'Swedru'],
    Eastern: ['Koforidua', 'Nkawkaw', 'Akosombo', 'Nsawam', 'Suhum', 'Akim Oda'],
    'Greater Accra': ['Accra', 'Tema', 'Madina', 'Kasoa', 'Ashaiman', 'Ablekuma', 'Adenta', 'Dodowa'],
    'North East': ['Nalerigu', 'Walewale', 'Gambaga'],
    Northern: ['Tamale', 'Yendi', 'Savelugu'],
    Oti: ['Dambai', 'Jasikan', 'Kadjebi', 'Nkwanta'],
    Savannah: ['Damongo', 'Bole', 'Salaga'],
    'Upper East': ['Bolgatanga', 'Bawku', 'Navrongo'],
    'Upper West': ['Wa', 'Lawra', 'Nandom', 'Jirapa'],
    Volta: ['Ho', 'Hohoe', 'Keta', 'Aflao', 'Kpandu'],
    Western: ['Takoradi', 'Sekondi', 'Tarkwa', 'Axim'],
    'Western North': ['Sefwi Wiawso', 'Bibiani', 'Sefwi Bekwai', 'Enchi', 'Juaboso'],
};

export const GHANA_REGIONS = Object.keys(GHANA_CITIES_BY_REGION);

export function citiesForRegion(region: string): string[] {
    return GHANA_CITIES_BY_REGION[region] ?? [];
}
