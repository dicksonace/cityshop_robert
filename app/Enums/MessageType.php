<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case CallOffer = 'call_offer';
    case CallAnswer = 'call_answer';
    case CallIce = 'call_ice';
    case CallEnd = 'call_end';
    case System = 'system';
}
