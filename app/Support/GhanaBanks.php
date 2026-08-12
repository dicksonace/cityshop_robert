<?php

namespace App\Support;

/**
 * Ghana bank options for wallet withdrawals (buyer + seller).
 */
class GhanaBanks
{
    /** @var array<string, string> slug => display name */
    public const OPTIONS = [
        'absa' => 'ABSA',
        'access' => 'Access Bank',
        'adb' => 'ADB',
        'adehyeman' => 'ADEHYEMAN',
        'advans' => 'ADVANS GHANA',
        'affinity' => 'AFFINITY',
        'arb_apex' => 'ARB APEX BANK',
        'bank_of_africa' => 'BANK of Africa',
        'bayport' => 'Bayport S&L',
        'bestpoint' => 'BESTPOINT',
        'bog' => 'BoG',
        'cal' => 'CAL Bank',
        'cbg' => 'CBG',
        'ecobank' => 'Ecobank',
        'fidelity' => 'Fidelity Bank',
        'firstbank' => 'FirstBank',
        'fnb' => 'FNB',
        'gcb' => 'GCB',
        'gtbank' => 'GT Bank',
        'letshego' => 'LETSHEGO',
        'nib' => 'NIB',
        'omnibsic' => 'OMNIBSIC',
        'opportunity' => 'Opportunity Int. S&L',
        'prudential' => 'Prudential Bank',
        'service_integrity' => 'Service Integrity S&L',
        'sinapi_aba' => 'Sinapi ABA',
        'societe_generale' => 'SOCIETE GENERALE',
        'stanbic' => 'Stanbic',
        'standard_chartered' => 'Standard Chartered',
        'transflow' => 'TransFlow',
        'uba' => 'UBA',
        'umb' => 'UMB',
        'zenith' => 'Zenith Bank',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::OPTIONS);
    }

    public static function isBank(?string $code): bool
    {
        return $code !== null && isset(self::OPTIONS[$code]);
    }

    public static function label(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'Bank';
        }

        return self::OPTIONS[$code] ?? str_replace('_', ' ', ucwords($code, '_'));
    }

    /** Accept a bank id or display name and return the canonical label, or null if unknown. */
    public static function resolveName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (isset(self::OPTIONS[$value])) {
            return self::OPTIONS[$value];
        }

        foreach (self::OPTIONS as $label) {
            if (strcasecmp($label, $value) === 0) {
                return $label;
            }
        }

        return null;
    }

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::codes());
    }
}
