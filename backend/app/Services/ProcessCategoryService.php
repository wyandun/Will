<?php

namespace App\Services;

use App\Models\ProcessCategory;

class ProcessCategoryService
{
    public function update(ProcessCategory $category, array $data): ProcessCategory
    {
        $category->update($data);

        return $category->fresh() ?? $category;
    }
}
