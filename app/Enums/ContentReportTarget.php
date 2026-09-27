<?php

namespace App\Enums;

enum ContentReportTarget: string
{
    case Product = 'product';
    case Message = 'message';
    case Status = 'status';
}
