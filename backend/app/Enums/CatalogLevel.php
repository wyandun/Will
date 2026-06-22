<?php

namespace App\Enums;

/**
 * Hierarchical level for a catalog item:
 *   - Bundle:      top-level grouping of services
 *   - Service:     a sellable service composed of deliverables
 *   - Deliverable: a concrete unit of work that belongs to a service
 */
enum CatalogLevel: string
{
    case Bundle = 'bundle';

    case Service = 'service';

    case Deliverable = 'deliverable';
}
