import { useState } from 'react';

import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { citiesForRegion, GHANA_REGIONS } from '@/lib/ghana-locations';
import { cn } from '@/lib/utils';

const OTHER = '__other__';

const selectClass =
    'flex h-10 w-full rounded-md border border-input bg-white px-3 py-2 text-base text-gray-900 ring-offset-background focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

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
    const cities = citiesForRegion(region).filter((name) => name !== 'Other');
    const cityDisabled = !region;
    const isCustomCity = Boolean(region && city && !cities.includes(city));
    const [pickingOther, setPickingOther] = useState(isCustomCity);
    const selectValue = pickingOther || isCustomCity ? OTHER : city;

    return (
        <div className={cn('space-y-4', className)}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <Label htmlFor="checkout-region">Region</Label>
                    <select
                        id="checkout-region"
                        value={region}
                        required
                        className={cn(selectClass, 'mt-1')}
                        onChange={(e) => {
                            const next = e.target.value;
                            onRegionChange(next);
                            setPickingOther(false);
                            onCityChange('');
                        }}
                    >
                        <option value="">Select region</option>
                        {GHANA_REGIONS.map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                    </select>
                    <InputError message={regionError} />
                </div>
                <div>
                    <Label htmlFor="checkout-city">City / Town</Label>
                    <select
                        id="checkout-city"
                        value={cityDisabled ? '' : selectValue}
                        required={!pickingOther && !isCustomCity}
                        disabled={cityDisabled}
                        className={cn(selectClass, 'mt-1')}
                        onChange={(e) => {
                            const next = e.target.value;
                            if (next === OTHER) {
                                setPickingOther(true);
                                onCityChange('');
                                return;
                            }
                            setPickingOther(false);
                            onCityChange(next);
                        }}
                    >
                        <option value="">{cityDisabled ? 'Select region first' : 'Select city / town'}</option>
                        {cities.map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                        <option value={OTHER}>Other (type your town)</option>
                    </select>
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
