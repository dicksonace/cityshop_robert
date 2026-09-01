import { useState } from 'react';

import InputError from '@/components/input-error';
import SearchableLocationSelect from '@/components/shop/searchable-location-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { citiesForRegion, GHANA_REGIONS, OTHER_CITY } from '@/lib/ghana-locations';
import { cn } from '@/lib/utils';

interface GhanaLocationFieldsProps {
    region: string;
    city: string;
    onRegionChange: (region: string) => void;
    onCityChange: (city: string) => void;
    regionError?: string;
    cityError?: string;
    className?: string;
}

export default function GhanaLocationFields({
    region,
    city,
    onRegionChange,
    onCityChange,
    regionError,
    cityError,
    className,
}: GhanaLocationFieldsProps) {
    const cities = citiesForRegion(region);
    const cityDisabled = !region;
    const isCustomCity = Boolean(region && city && !cities.includes(city));
    const [pickingOther, setPickingOther] = useState(isCustomCity);
    const cityOptions = cities.filter((name) => name !== OTHER_CITY);
    const selectValue = pickingOther || isCustomCity ? OTHER_CITY : city;

    return (
        <div className={cn('space-y-4', className)}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <SearchableLocationSelect
                        id="checkout-region"
                        label="Region"
                        value={region}
                        options={GHANA_REGIONS}
                        placeholder="Select region"
                        searchPlaceholder="Search region"
                        onChange={(next) => {
                            onRegionChange(next);
                            setPickingOther(false);
                            onCityChange('');
                        }}
                    />
                    <InputError message={regionError} />
                </div>
                <div>
                    <SearchableLocationSelect
                        id="checkout-city"
                        label="City / Town"
                        value={cityDisabled ? '' : selectValue}
                        options={[...cityOptions, OTHER_CITY]}
                        placeholder={cityDisabled ? 'Select region first' : 'Select city / town'}
                        searchPlaceholder="Search city / town"
                        disabled={cityDisabled}
                        onChange={(next) => {
                            if (next === OTHER_CITY) {
                                setPickingOther(true);
                                onCityChange('');
                                return;
                            }
                            setPickingOther(false);
                            onCityChange(next);
                        }}
                    />
                    <InputError message={!pickingOther && !isCustomCity ? cityError : undefined} />
                </div>
            </div>
            {(pickingOther || isCustomCity) && region && (
                <div>
                    <Label htmlFor="checkout-city-other">Your city / town</Label>
                    <Input
                        id="checkout-city-other"
                        value={city}
                        required
                        placeholder="Type your city or town"
                        className="mt-1"
                        onChange={(e) => onCityChange(e.target.value)}
                    />
                    <InputError message={cityError} />
                </div>
            )}
        </div>
    );
}
