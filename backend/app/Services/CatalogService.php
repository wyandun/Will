<?php

namespace App\Services;

use App\Enums\CatalogLevel;
use App\Models\CatalogItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatalogService
{
    /**
     * Build the full catalog tree with bundles → services → deliverables,
     * plus a flat services list and aggregate counts.
     *
     * @return array{
     *     bundles: \Illuminate\Database\Eloquent\Collection<int, CatalogItem>,
     *     services: \Illuminate\Database\Eloquent\Collection<int, CatalogItem>,
     *     counts: array{bundles:int, services:int, deliverables:int}
     * }
     */
    public function tree(): array
    {
        // Eager-load two nesting levels: bundle → services → deliverables.
        $bundles = CatalogItem::bundles()
            ->with(['children' => function ($q) {
                $q->with('children');
            }])
            ->orderBy('order_index')
            ->get();

        // Services as a flat list (each with its deliverable children) is useful
        // when assigning deliverables to a service without scrolling through bundles.
        $services = CatalogItem::services()
            ->with('children')
            ->orderBy('order_index')
            ->get();

        $counts = [
            'bundles' => CatalogItem::bundles()->count(),
            'services' => CatalogItem::services()->count(),
            'deliverables' => CatalogItem::deliverables()->count(),
        ];

        return [
            'bundles' => $bundles,
            'services' => $services,
            'counts' => $counts,
        ];
    }

    /**
     * List items filtered by level. Bundles and services come with their children eager-loaded.
     */
    public function list(CatalogLevel $level): Collection
    {
        $query = CatalogItem::where('level', $level->value)->orderBy('order_index');

        if ($level === CatalogLevel::Bundle) {
            $query->with(['children' => function ($q) {
                $q->with('children');
            }]);
        }

        if ($level === CatalogLevel::Service) {
            $query->with('children');
        }

        return $query->get();
    }

    /**
     * Create a new catalog item. When a service receives `deliverable_ids` or a
     * bundle receives `service_ids`, those items are re-parented to this new item.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CatalogItem
    {
        return DB::transaction(function () use ($data) {
            $deliverableIds = $data['deliverable_ids'] ?? null;
            $serviceIds = $data['service_ids'] ?? null;
            unset($data['deliverable_ids'], $data['service_ids']);

            $item = CatalogItem::create($data);

            $this->reassignChildren($item, $deliverableIds, $serviceIds);

            Log::info('Catalog item created', [
                'catalog_item_id' => $item->id,
                'level' => $item->level,
                'parent_id' => $item->parent_id,
            ]);

            return $item->fresh(['parent', 'children']);
        });
    }

    /**
     * Update a catalog item. Honours the same `deliverable_ids` / `service_ids`
     * reassignment semantics as create().
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CatalogItem $item, array $data): CatalogItem
    {
        return DB::transaction(function () use ($item, $data) {
            $deliverableIds = array_key_exists('deliverable_ids', $data) ? $data['deliverable_ids'] : null;
            $serviceIds = array_key_exists('service_ids', $data) ? $data['service_ids'] : null;
            $reassignDeliverables = array_key_exists('deliverable_ids', $data);
            $reassignServices = array_key_exists('service_ids', $data);
            unset($data['deliverable_ids'], $data['service_ids']);

            $item->update($data);

            if ($reassignDeliverables || $reassignServices) {
                $this->reassignChildren(
                    $item,
                    $reassignDeliverables ? ($deliverableIds ?? []) : null,
                    $reassignServices ? ($serviceIds ?? []) : null,
                );
            }

            Log::info('Catalog item updated', [
                'catalog_item_id' => $item->id,
                'changes' => array_keys($data),
            ]);

            return $item->fresh(['parent', 'children']);
        });
    }

    /**
     * Delete a catalog item.
     *
     * When $cascadeChildren is true and the item is a service, its deliverable
     * children are hard-deleted along with it. Otherwise children are orphaned
     * (parent_id = null) so they remain accessible under "Uncategorized".
     */
    public function delete(CatalogItem $item, bool $cascadeChildren = false): void
    {
        DB::transaction(function () use ($item, $cascadeChildren) {
            if ($cascadeChildren && $item->level === CatalogLevel::Service) {
                $item->children()->delete();
            } else {
                $item->children()->update(['parent_id' => null]);
            }

            $itemId = $item->id;
            $item->delete();

            Log::info('Catalog item deleted', [
                'catalog_item_id' => $itemId,
                'cascade_children' => $cascadeChildren,
            ]);
        });
    }

    /**
     * Reassign children by setting their parent_id to this item.
     *
     * @param  array<int>|null  $deliverableIds  IDs to attach as deliverable children of a service
     * @param  array<int>|null  $serviceIds  IDs to attach as service children of a bundle
     */
    private function reassignChildren(CatalogItem $parent, ?array $deliverableIds, ?array $serviceIds): void
    {
        if ($deliverableIds !== null && $parent->level === CatalogLevel::Service) {
            CatalogItem::deliverables()
                ->whereIn('id', $deliverableIds)
                ->update(['parent_id' => $parent->id]);
        }

        if ($serviceIds !== null && $parent->level === CatalogLevel::Bundle) {
            CatalogItem::services()
                ->whereIn('id', $serviceIds)
                ->update(['parent_id' => $parent->id]);
        }
    }
}
