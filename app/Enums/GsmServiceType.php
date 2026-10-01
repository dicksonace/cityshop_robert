<?php

namespace App\Enums;

enum GsmServiceType: string
{
    case Imei = 'imei';
    case Server = 'server';
    case Remote = 'remote';
    case Credit = 'credit';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Imei => 'IMEI Service',
            self::Server => 'Server Service',
            self::Remote => 'Remote Service',
            self::Credit => 'Credit | Box Activation',
            self::File => 'File Service',
        };
    }

    /**
     * Place-order types (GSM Player): IMEI, Server, Remote, File, plus Credit | Box Activation.
     *
     * @return list<self>
     */
    public static function groups(): array
    {
        return [self::Imei, self::Server, self::Remote, self::File, self::Credit];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::groups());
    }
}
