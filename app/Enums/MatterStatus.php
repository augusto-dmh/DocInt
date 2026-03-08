<?php

namespace App\Enums;

enum MatterStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case OnHold = 'on_hold';
}
