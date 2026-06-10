<?php

namespace App\Services;

use App\Models\Process;
use App\Models\SubProcess;

class SubProcessService
{
    public function create(Process $process, array $data): SubProcess
    {
        $count = SubProcess::where('process_id', $process->id)->count();
        $code = $process->code.'-P'.str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
        $nextIndex = (SubProcess::where('process_id', $process->id)->max('order_index') ?? 0) + 1;

        return SubProcess::create([
            'process_id' => $process->id,
            'code' => $code,
            'name_es' => $data['name_es'],
            'name_en' => $data['name_en'],
            'description' => $data['description'] ?? null,
            'order_index' => $nextIndex,
        ]);
    }

    public function update(SubProcess $subProcess, array $data): SubProcess
    {
        $subProcess->update($data);

        return $subProcess->fresh() ?? $subProcess;
    }

    public function delete(SubProcess $subProcess): void
    {
        $subProcess->delete();
    }
}
