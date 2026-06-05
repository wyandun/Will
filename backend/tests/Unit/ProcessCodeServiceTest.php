<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Process;
use App\Models\ProcessCategory;
use App\Services\ProcessService;
use App\Services\SubProcessService;
use App\Services\SubSubProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

class ProcessCodeServiceTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    private function valueChainCategory(): ProcessCategory
    {
        $map = $this->createMapWithCategories($this->createCompany());

        return $this->categoryOfType($map, ProcessCategory::TYPE_VALUE_CHAIN);
    }

    public function test_process_code_is_uppercased_and_order_increments(): void
    {
        $service = new ProcessService;
        $category = $this->valueChainCategory();

        $a = $service->create($category, ['code' => 'gth', 'name_es' => 'A', 'name_en' => 'A', 'description' => 'd']);
        $b = $service->create($category, ['code' => 'sc', 'name_es' => 'B', 'name_en' => 'B']);

        $this->assertSame('GTH', $a->code);
        $this->assertSame('d', $a->description);
        $this->assertSame(1, $a->order_index);
        $this->assertSame(2, $b->order_index);
    }

    public function test_process_rejects_duplicate_code_within_same_map(): void
    {
        $service = new ProcessService;
        $category = $this->valueChainCategory();
        $service->create($category, ['code' => 'GTH', 'name_es' => 'A', 'name_en' => 'A']);

        $this->expectException(ValidationException::class);
        $service->create($category, ['code' => 'GTH', 'name_es' => 'B', 'name_en' => 'B']);
    }

    public function test_sub_process_code_pattern(): void
    {
        $service = new SubProcessService;
        $category = $this->valueChainCategory();
        $process = Process::factory()->create(['category_id' => $category->id, 'code' => 'GTH']);

        $first = $service->create($process, ['name_es' => 'A', 'name_en' => 'A']);
        $second = $service->create($process, ['name_es' => 'B', 'name_en' => 'B']);

        $this->assertSame('GTH-P01', $first->code);
        $this->assertSame('GTH-P02', $second->code);
    }

    public function test_sub_sub_process_code_pattern(): void
    {
        $subProcessService = new SubProcessService;
        $subSubService = new SubSubProcessService;
        $category = $this->valueChainCategory();
        $process = Process::factory()->create(['category_id' => $category->id, 'code' => 'GTH']);
        $subProcess = $subProcessService->create($process, ['name_es' => 'A', 'name_en' => 'A']);

        $first = $subSubService->create($subProcess, ['name_es' => 'S1', 'name_en' => 'S1']);
        $second = $subSubService->create($subProcess, ['name_es' => 'S2', 'name_en' => 'S2']);

        $this->assertSame('GTH-P01-S01', $first->code);
        $this->assertSame('GTH-P01-S02', $second->code);
    }
}
