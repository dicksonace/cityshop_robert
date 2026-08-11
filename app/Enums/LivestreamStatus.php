<?php

namespace App\Enums;

enum LivestreamStatus: string
{
    case Live = 'live';
    case Ended = 'ended';
}
