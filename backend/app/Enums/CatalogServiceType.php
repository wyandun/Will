<?php

namespace App\Enums;

/**
 * Commercial type of a service-level catalog item.
 */
enum CatalogServiceType: string
{
    case Individual = 'individual';

    case Package = 'package';

    case Retainer = 'retainer';
}
