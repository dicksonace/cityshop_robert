<?php

namespace App\Enums;

enum GsmServiceType: string
{
    case Imei = 'imei';
    case Server = 'server';
    case Remote = 'remote';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Imei => 'IMEI Service',
            self::Server => 'Server Service',
            self::Remote => 'Remote Service',
            self::File => 'File Service',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::cases());
    }
}
