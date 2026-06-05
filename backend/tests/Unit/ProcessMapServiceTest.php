<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Franchise;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Services\ProcessMapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

class ProcessMapServiceTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    private ProcessMapService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProcessMapService;
    }

    public function test_create_seeds_the_three_fixed_categories(): void
    {
        $company = $this->createCompany();

        $map = $this->service->create([
            'company_id' => $company->id,
            'type' => 'franquiciadora',
            'name_es' => 'Mapa',
            'name_en' => 'Map',
        ]);

        $types = $map->categories()->pluck('type')->sort()->values()->all();
        $this->assertCount(3, $types);
        $this->assertSame([
            ProcessCategory::TYPE_STRATEGIC,
            ProcessCategory::TYPE_SUPPORT,
            ProcessCategory::TYPE_VALUE_CHAIN,
        ], $types);
    }

    public function test_list_filters_by_company(): void
    {
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();
        ProcessMap::factory()->count(2)->forCompany($companyA)->create();
        ProcessMap::factory()->forCompany($companyB)->create();

        $result = $this->service->list(['company_id' => $companyA->id]);

        $this->assertSame(2, $result->total());
    }

    public function test_list_filters_by_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        ProcessMap::factory()->count(3)->forCompany($company)->create();
        ProcessMap::factory()->forCompany($this->createCompany())->create();

        $result = $this->service->list(['franchise_id' => $franchise->id]);

        $this->assertSame(3, $result->total());
    }

    public function test_list_respects_per_page(): void
    {
        ProcessMap::factory()->count(5)->create();

        $result = $this->service->list(['per_page' => 2]);

        $this->assertSame(2, $result->perPage());
        $this->assertSame(5, $result->total());
        $this->assertCount(2, $result->items());
    }
}
