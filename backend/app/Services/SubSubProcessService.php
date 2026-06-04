<?php

namespace App\Services;

use App\Models\SubProcess;
use App\Models\SubSubProcess;

class SubSubProcessService
{
    public function create(SubProcess $subProcess, array $data): SubSubProcess
    {
        $count = SubSubProcess::where('sub_process_id', $subProcess->id)->count();
        $code = $subProcess->code.'-S'.str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
        $nextIndex = (SubSubProcess::where('sub_process_id', $subProcess->id)->max('order_index') ?? 0) + 1;

        return SubSubProcess::create([
            'sub_process_id' => $subProcess->id,
            'code' => $code,
            'name_es' => $data['name_es'],
            'name_en' => $data['name_en'],
            'order_index' => $nextIndex,
        ]);
    }

    public function update(SubSubProcess $subSubProcess, array $data): SubSubProcess
    {
        $subSubProcess->update($data);

        return $subSubProcess->fresh() ?? $subSubProcess;
    }

    public function delete(SubSubProcess $subSubProcess): void
    {
        $subSubProcess->delete();
    }
}
