<?php

namespace App\Services;

use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use Illuminate\Validation\ValidationException;

class NodeLinkService
{
    /** @var list<string> */
    public const TYPES = ['url', 'document', 'subprocess'];

    /**
     * Persist node links for a sub-process or sub-sub-process.
     *
     * @param  array<string, array{type: string, value: int|string}>  $links
     */
    public function update(SubProcess|SubSubProcess $model, array $links): SubProcess|SubSubProcess
    {
        if (empty($links)) {
            $model->node_links = null;
            $model->save();

            return $model;
        }

        $this->assertReferences($model, $links);

        $normalized = [];
        foreach ($links as $nodeId => $link) {
            $value = $link['value'];
            if (in_array($link['type'], ['document', 'subprocess'], true)) {
                $value = (int) $value;
            }
            $normalized[$nodeId] = ['type' => $link['type'], 'value' => $value];
        }

        $model->node_links = $normalized;
        $model->save();

        return $model;
    }

    /**
     * Validate that referenced document/subprocess IDs belong to the correct scope.
     *
     * @param  array<string, array{type: string, value: int|string}>  $links
     *
     * @throws ValidationException
     */
    private function assertReferences(SubProcess|SubSubProcess $model, array $links): void
    {
        $documentIds = [];
        $subprocessIds = [];

        foreach ($links as $link) {
            if ($link['type'] === 'document') {
                $documentIds[] = (int) $link['value'];
            } elseif ($link['type'] === 'subprocess') {
                $subprocessIds[] = (int) $link['value'];
            }
        }

        if (! empty($documentIds)) {
            $ownIds = $model->documents()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $invalid = array_diff($documentIds, $ownIds);

            if (! empty($invalid)) {
                throw ValidationException::withMessages([
                    'node_links' => ['One or more documents do not belong to this process level.'],
                ]);
            }
        }

        if (! empty($subprocessIds)) {
            $mapId = $this->resolveMapId($model);

            if ($mapId === null) {
                throw ValidationException::withMessages([
                    'node_links' => ['Cannot resolve the process map for subprocess validation.'],
                ]);
            }

            $uniqueIds = array_unique($subprocessIds);
            $validCount = SubProcess::whereIn('id', $uniqueIds)
                ->whereHas('process.category', fn ($q) => $q->where('process_map_id', $mapId))
                ->count();

            if ($validCount !== count($uniqueIds)) {
                throw ValidationException::withMessages([
                    'node_links' => ['One or more subprocesses do not belong to this process map.'],
                ]);
            }
        }
    }

    private function resolveMapId(SubProcess|SubSubProcess $model): ?int
    {
        if ($model instanceof SubProcess) {
            return $this->resolveMapIdFromSubProcess($model);
        }

        $subProcess = $model->subProcess instanceof SubProcess
            ? $model->subProcess
            : SubProcess::find($model->sub_process_id);

        if (! $subProcess instanceof SubProcess) {
            return null;
        }

        return $this->resolveMapIdFromSubProcess($subProcess);
    }

    private function resolveMapIdFromSubProcess(SubProcess $subProcess): ?int
    {
        $process = $subProcess->process instanceof Process
            ? $subProcess->process
            : Process::find($subProcess->process_id);

        if (! $process instanceof Process) {
            return null;
        }

        $category = $process->category instanceof ProcessCategory
            ? $process->category
            : ProcessCategory::find($process->category_id);

        if (! $category instanceof ProcessCategory) {
            return null;
        }

        return (int) $category->process_map_id;
    }
}
