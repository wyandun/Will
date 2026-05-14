<?php

namespace App\Services;

use App\Models\NewsArticle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiNewsService
{
    private string $apiKey;

    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');
    }

    /**
     * Send up to 15 articles to Claude Haiku.
     * Haiku selects the 10 most useful and writes a 3-sentence summary for each.
     * Updates each NewsArticle in-place and marks them pending_review or rejected.
     *
     * @param  Collection<int, NewsArticle>|list<NewsArticle>  $articles
     */
    public function processArticles(iterable $articles): void
    {
        $articleList = collect($articles)->values();

        if ($articleList->isEmpty()) {
            return;
        }

        $prompt = $this->buildPrompt($articleList->all());

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 2048,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('AiNewsService: Anthropic API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Mark all as pending_review without summary so they still show up
            foreach ($articleList as $article) {
                $article->update(['status' => 'pending_review', 'ai_selected' => false]);
            }

            return;
        }

        $text = $response->json('content.0.text', '');
        $this->applyResults($articleList->all(), $text);
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    /**
     * @param  list<NewsArticle>  $articles
     */
    private function buildPrompt(array $articles): string
    {
        $numbered = '';
        foreach ($articles as $i => $article) {
            $n = $i + 1;
            $desc = mb_substr($article->description ?? '', 0, 300);
            $numbered .= "[{$n}] {$article->title}\nSource: {$article->source}\n{$desc}\n\n";
        }

        return <<<PROMPT
You are an editorial assistant for a portal that serves Latino small business owners in the USA.
Below is a numbered list of news articles (up to 15). Your job is to:
1. Select the 10 most relevant and actionable articles for small business owners (focus on: taxes, labor, franchises, construction, restaurants, immigration, loans, permits, inflation, tariffs, supply chain).
2. For each selected article write a 3-sentence summary:
   - Sentence 1: What happened.
   - Sentence 2: Why it matters to a small business owner.
   - Sentence 3: One concrete action they can take.

Return ONLY a JSON array. No extra text. Format:
[
  {"index": <number>, "summary": "<3-sentence summary>"},
  ...
]

Articles:
{$numbered}
PROMPT;
    }

    /**
     * Parse Haiku's JSON response and update each article's status + summary.
     *
     * @param  list<NewsArticle>  $articles
     */
    private function applyResults(array $articles, string $rawText): void
    {
        $selected = $this->parseJson($rawText);

        // Index the selected results by their 1-based position
        $byIndex = [];
        foreach ($selected as $item) {
            if (isset($item['index'], $item['summary'])) {
                $byIndex[(int) $item['index']] = (string) $item['summary'];
            }
        }

        foreach ($articles as $i => $article) {
            $pos = $i + 1;
            $summary = $byIndex[$pos] ?? null;

            $article->update([
                'ai_summary' => $summary,
                'ai_selected' => $summary !== null,
                'status' => $summary !== null ? 'pending_review' : 'rejected',
            ]);
        }
    }

    /**
     * Extract the first valid JSON array from the model response.
     *
     * @return list<array<string, mixed>>
     */
    private function parseJson(string $text): array
    {
        // Strip markdown code fences if present
        $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $text) ?? $text;
        $clean = trim($clean);

        $start = strpos($clean, '[');
        $end = strrpos($clean, ']');

        if ($start === false || $end === false) {
            Log::warning('AiNewsService: no JSON array found in response', ['raw' => mb_substr($text, 0, 500)]);

            return [];
        }

        $json = substr($clean, $start, $end - $start + 1);

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException $e) {
            Log::warning('AiNewsService: JSON parse error', ['error' => $e->getMessage(), 'json' => mb_substr($json, 0, 500)]);

            return [];
        }
    }
}
