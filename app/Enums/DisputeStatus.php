<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case ResolvedBuyer = 'resolved_buyer';
    case ResolvedSeller = 'resolved_seller';
    case Closed = 'closed';
}
