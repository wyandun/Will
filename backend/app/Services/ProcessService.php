<?php

namespace App\Services;

use App\Models\Process;
use App\Models\ProcessCategory;
use Illuminate\Validation\ValidationException;

class ProcessService
{
    public function create(ProcessCategory $category, array $data): Process
    {
        $code = strtoupper($data['code']);

        $exists = Process::whereHas('category', function ($q) use ($category): void {
            $q->where('process_map_id', $category->process_map_id);
        })->where('code', $code)->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => 'Code already exists in this map.']);
        }

        $nextIndex = (Process::where('category_id', $category->id)->max('order_index') ?? 0) + 1;

        return Process::create([
            'category_id' => $category->id,
            'code' => $code,
            'name_es' => $data['name_es'],
            'name_en' => $data['name_en'],
            'order_index' => $nextIndex,
        ]);
    }

    public function update(Process $process, array $data): Process
    {
        $process->update($data);

        return $process->fresh() ?? $process;
    }

    public function delete(Process $process): void
    {
        $process->delete();
    }
}
