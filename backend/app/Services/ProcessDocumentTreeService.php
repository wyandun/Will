<?php

namespace App\Services;

use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessDocument;
use App\Models\ProcessMap;
use App\Models\Repository;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use Illuminate\Database\Eloquent\Collection;

class ProcessDocumentTreeService
{
    /**
     * Build the process document tree for a repository.
     *
     * Finds the active ProcessMap for the repository's company and
     * returns the full hierarchy (categories → processes → subProcesses → documents)
     * with document counts per category.
     *
     * @return array<string, mixed>|null null when no process map exists for the company
     */
    public function treeForRepository(Repository $repository): ?array
    {
        $processMap = ProcessMap::where('company_id', $repository->company_id)
            ->where('is_active', true)
            ->with([
                'categories' => fn ($q) => $q->orderBy('order_index'),
                'categories.processes' => fn ($q) => $q->orderBy('order_index'),
                'categories.processes.subProcesses' => fn ($q) => $q->orderBy('order_index'),
                'categories.processes.subProcesses.documents',
                'categories.processes.subProcesses.subSubProcesses' => fn ($q) => $q->orderBy('order_index'),
                'categories.processes.subProcesses.subSubProcesses.documents',
            ])
            ->first();

        if ($processMap === null) {
            return null;
        }

        /** @var Collection<int, ProcessCategory> $categoryModels */
        $categoryModels = $processMap->categories;

        $categories = $categoryModels->map(fn (ProcessCategory $c) => $this->formatCategory($c))->values();

        return [
            'process_map_id' => $processMap->id,
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCategory(ProcessCategory $category): array
    {
        /** @var Collection<int, Process> $processModels */
        $processModels = $category->processes;
        $processes = $processModels->map(fn (Process $p) => $this->formatProcess($p))->values();

        return [
            'id' => $category->id,
            'type' => $category->type,
            'name_es' => $category->name_es,
            'name_en' => $category->name_en,
            'docs_count' => (int) $processes->sum('docs_count'),
            'processes' => $processes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProcess(Process $process): array
    {
        /** @var Collection<int, SubProcess> $subs */
        $subs = $process->subProcesses;
        $subProcesses = $subs->map(fn (SubProcess $s) => $this->formatSubProcess($s))->values();

        return [
            'id' => $process->id,
            'code' => $process->code,
            'name_es' => $process->name_es,
            'name_en' => $process->name_en,
            'docs_count' => (int) $subProcesses->sum('docs_count'),
            'sub_processes' => $subProcesses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSubProcess(SubProcess $sub): array
    {
        $subDocs = $sub->documents->map(fn (ProcessDocument $d) => $this->formatDocument($d))->values();

        /** @var Collection<int, SubSubProcess> $ssubList */
        $ssubList = $sub->subSubProcesses;
        $subSubProcesses = $ssubList->map(fn (SubSubProcess $s) => $this->formatSubSubProcess($s))->values();

        return [
            'id' => $sub->id,
            'code' => $sub->code,
            'name_es' => $sub->name_es,
            'name_en' => $sub->name_en,
            'docs_count' => $subDocs->count() + (int) $subSubProcesses->sum('docs_count'),
            'documents' => $subDocs,
            'sub_sub_processes' => $subSubProcesses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSubSubProcess(SubSubProcess $ssub): array
    {
        $docs = $ssub->documents->map(fn (ProcessDocument $d) => $this->formatDocument($d))->values();

        return [
            'id' => $ssub->id,
            'code' => $ssub->code,
            'name_es' => $ssub->name_es,
            'name_en' => $ssub->name_en,
            'docs_count' => $docs->count(),
            'documents' => $docs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDocument(ProcessDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'code' => $doc->code,
            'type' => $doc->type,
            'title_es' => $doc->title_es,
            'title_en' => $doc->title_en,
            'version' => $doc->version,
            'file_url' => $doc->file_url,
        ];
    }
}
