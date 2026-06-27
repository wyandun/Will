<?php

namespace App\Services;

use App\Enums\CatalogLevel;
use App\Enums\ProjectDeliverableStatus;
use App\Enums\ProjectStatus;
use App\Models\CatalogItem;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectService
{
    /**
     * Working hours per business day used to convert estimated_hours → days.
     */
    private const HOURS_PER_DAY = 8;

    /**
     * Create a project and auto-generate its deliverables from the catalog.
     *
     * Flow:
     *   1. Resolve which catalog deliverables to include based on the assigned
     *      catalog item's level (bundle → all descendants; service → children;
     *      deliverable → itself).
     *   2. Create the Project record.
     *   3. Calculate sequential dates (each deliverable starts on the next
     *      business day after the previous one ends).
     *   4. Bulk-insert ProjectDeliverable rows.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $catalogItem = CatalogItem::with('children.children')->findOrFail($data['catalog_item_id']);
            $deliverables = $this->resolveDeliverables($catalogItem);

            $project = Project::create([
                'company_id' => $data['company_id'],
                'franchise_id' => $data['franchise_id'],
                'catalog_item_id' => $data['catalog_item_id'],
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'notes' => $data['notes'] ?? null,
                'status' => ProjectStatus::Active,
            ]);

            $this->generateDeliverables($project, $deliverables, Carbon::parse($data['start_date']));

            Log::info('Project created', [
                'project_id' => $project->id,
                'company_id' => $project->company_id,
                'type' => $project->type,
                'deliverables_count' => $deliverables->count(),
            ]);

            return $project->load(['company', 'franchise', 'catalogItem', 'deliverables']);
        });
    }

    /**
     * Resolve the ordered list of deliverable-level catalog items for a given
     * root item.
     *
     * @return Collection<int, CatalogItem>
     */
    private function resolveDeliverables(CatalogItem $root): Collection
    {
        return match ($root->level) {
            CatalogLevel::Bundle => $this->deliverablesFromBundle($root),
            CatalogLevel::Service => $this->deliverablesFromService($root),
            CatalogLevel::Deliverable => collect([$root]),
        };
    }

    /**
     * Flatten all deliverables across all services inside a bundle.
     *
     * @return Collection<int, CatalogItem>
     */
    private function deliverablesFromBundle(CatalogItem $bundle): Collection
    {
        $services = $bundle->relationLoaded('children')
            ? $bundle->children
            : $bundle->children()->with('children')->orderBy('order_index')->get();

        return $services->flatMap(fn (CatalogItem $service) => $this->deliverablesFromService($service));
    }

    /**
     * Return ordered deliverable children of a service.
     *
     * @return Collection<int, CatalogItem>
     */
    private function deliverablesFromService(CatalogItem $service): Collection
    {
        return $service->relationLoaded('children')
            ? $service->children->sortBy('order_index')->values()
            : $service->children()->where('level', CatalogLevel::Deliverable->value)->orderBy('order_index')->get();
    }

    /**
     * Create ProjectDeliverable rows with sequentially calculated dates.
     *
     * Each deliverable starts on the next available business day after the
     * previous one ends. estimated_hours from the catalog is converted to
     * business days (ceiling) at HOURS_PER_DAY hours per day.
     *
     * @param  Collection<int, CatalogItem>  $catalogDeliverables
     */
    private function generateDeliverables(
        Project $project,
        Collection $catalogDeliverables,
        Carbon $startDate
    ): void {
        $currentStart = $this->nextBusinessDay($startDate->copy()->subDay());
        $order = 0;

        $rows = [];

        foreach ($catalogDeliverables as $catalogItem) {
            $hours = (float) ($catalogItem->estimated_hours ?? 0);
            $days = $hours > 0 ? (int) ceil($hours / self::HOURS_PER_DAY) : 1;

            $deliverableStart = $currentStart->copy();
            $deliverableEnd = $this->addBusinessDays($deliverableStart->copy(), $days - 1);

            $rows[] = [
                'project_id' => $project->id,
                'catalog_item_id' => $catalogItem->id,
                'name' => $catalogItem->name_es,
                'phase' => $catalogItem->parent?->name_es,
                'estimated_start_date' => $deliverableStart->toDateString(),
                'estimated_end_date' => $deliverableEnd->toDateString(),
                'status' => ProjectDeliverableStatus::Pending->value,
                'order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Next deliverable starts on the business day after this one ends
            $currentStart = $this->nextBusinessDay($deliverableEnd);
        }

        if (! empty($rows)) {
            ProjectDeliverable::insert($rows);
        }
    }

    /**
     * Advance $date forward until it lands on a business day (Mon–Fri).
     */
    private function nextBusinessDay(Carbon $date): Carbon
    {
        $next = $date->copy()->addDay();

        while ($next->isWeekend()) {
            $next->addDay();
        }

        return $next;
    }

    /**
     * Add $days business days to $date (skipping weekends).
     */
    private function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy();

        for ($i = 0; $i < $days; $i++) {
            $result->addDay();
            while ($result->isWeekend()) {
                $result->addDay();
            }
        }

        return $result;
    }
}
