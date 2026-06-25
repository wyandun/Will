<?php

namespace App\Enums;

enum DocumentSection: string
{
    case SETUP = 'setup';
    case PROCESS = 'process';
    case RECORD = 'record';
}
