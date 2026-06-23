<?php

namespace App\Http\Requests;

use App\Services\NodeLinkService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNodeLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('subProcess') ?? $this->route('subSubProcess');

        return $model !== null && (bool) $this->user()?->can('update', $model);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'node_links' => ['present', 'array', 'max:500'],
            'node_links.*.type' => ['required', 'string', 'in:'.implode(',', NodeLinkService::TYPES)],
            'node_links.*.value' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $links = $this->input('node_links');

            if (! is_array($links)) {
                return;
            }

            foreach ($links as $nodeId => $link) {
                if (! is_string($nodeId) || ! preg_match('/^[A-Za-z0-9_.\\-]+$/', $nodeId) || strlen($nodeId) > 255) {
                    $v->errors()->add('node_links', "Invalid node ID: {$nodeId}");

                    continue;
                }

                if (! is_array($link)) {
                    continue;
                }

                $type = $link['type'] ?? null;
                $value = $link['value'] ?? null;

                if ($type === 'url') {
                    if (! is_string($value) || ! preg_match('/^https?:\/\//', $value)) {
                        $v->errors()->add("node_links.{$nodeId}.value", 'URL must start with http:// or https://');
                    } elseif (! filter_var($value, FILTER_VALIDATE_URL) || strlen($value) > 2048) {
                        $v->errors()->add("node_links.{$nodeId}.value", 'The URL is invalid or exceeds 2048 characters.');
                    }
                } elseif (in_array($type, ['document', 'subprocess'], true)) {
                    if (! is_numeric($value) || (int) $value != $value) {
                        $v->errors()->add("node_links.{$nodeId}.value", "Value for {$type} must be an integer.");
                    }
                }
            }
        });
    }
}
