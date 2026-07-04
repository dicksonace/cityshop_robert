<?php

namespace App\Enums;

enum SellerReportReason: string
{
    case Scam = 'scam';
    case Counterfeit = 'counterfeit';
    case Harassment = 'harassment';
    case PoorService = 'poor_service';
    case ProhibitedItems = 'prohibited_items';
    case FakeListings = 'fake_listings';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Scam => 'Scam or fraud',
            self::Counterfeit => 'Counterfeit or fake products',
            self::Harassment => 'Harassment or abuse',
            self::PoorService => 'Poor service or unresponsive seller',
            self::ProhibitedItems => 'Prohibited or illegal items',
            self::FakeListings => 'Misleading or fake listings',
            self::Other => 'Other',
        };
    }
}
