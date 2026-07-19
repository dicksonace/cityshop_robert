<?php

namespace App\Enums;

enum FundsReleaseStatus: string
{
    case NotApplicable = 'not_applicable';
    case Pending = 'pending';
    case Released = 'released';
    case Held = 'held';
}
