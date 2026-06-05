<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores a BPMN diagram on a process-tree model.
 *
 * The XML is always written to a language-specific column — bpmn_xml_es for
 * Spanish, bpmn_xml_en for English. There is intentionally NO single combined
 * bpmn_xml field: each language keeps its own diagram.
 */
class BpmnService
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $model  a SubProcess or SubSubProcess
     * @return TModel
     */
    public function store(Model $model, string $lang, string $xml): Model
    {
        $column = $lang === 'en' ? 'bpmn_xml_en' : 'bpmn_xml_es';

        $model->update([$column => $xml]);

        return $model->fresh() ?? $model;
    }
}
