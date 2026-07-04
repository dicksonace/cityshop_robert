<?php

namespace App\Enums;

enum SellerReportStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
