<?php

namespace App\Enums;

enum SetupCategory: string
{
    case LEGAL = 'legal';
    case HR = 'hr';
    case CERTIFICATES = 'certificates';
    case MARKETING = 'marketing';
    case SOPS = 'sops';
}
