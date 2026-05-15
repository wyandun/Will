<?php

namespace App\Services;

use App\Enums\NewsArticleStatus;
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
     * Process up to 15 articles through Claude Haiku.
     * Step 1: Send all articles to Claude with the selection prompt → get back
     *         a JSON array of 1-based indices (up to 10).
     * Step 2: For each selected article, call Claude with the summarization
     *         prompt to produce a 3-sentence summary.
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

        // Step 1: Select the most relevant articles
        $selectedIndices = $this->selectArticles($articleList->all());

        if (empty($selectedIndices)) {
            Log::error('AiNewsService: selectArticles returned no indices — leaving articles in pending_ai for retry');
            throw new \RuntimeException('AI selection returned no indices');
        }

        // Step 2: Summarize each selected article individually
        foreach ($articleList as $i => $article) {
            $pos = $i + 1; // 1-based index
            $isSelected = in_array($pos, $selectedIndices, true);

            if (! $isSelected) {
                $article->update([
                    'ai_summary' => null,
                    'ai_selected' => false,
                    'status' => NewsArticleStatus::Rejected->value,
                ]);

                continue;
            }

            $summary = $this->summarizeArticle($article);
            $summaryEs = $this->summarizeArticleEs($article);

            $article->update([
                'ai_summary' => $summary !== null ? $this->stripMarkdown($summary) : null,
                'ai_summary_es' => $summaryEs !== null ? $this->stripMarkdown($summaryEs) : null,
                'ai_selected' => true,
                'status' => NewsArticleStatus::PendingReview->value,
            ]);
        }
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    /**
     * Call Claude with the selection prompt.
     * Returns an array of 1-based indices of the selected articles.
     *
     * @param  list<NewsArticle>  $articles
     * @return list<int>
     */
    private function selectArticles(array $articles): array
    {
        $numbered = '';
        foreach ($articles as $i => $article) {
            $n = $i + 1;
            $desc = mb_substr($article->description ?? '', 0, 300);
            $numbered .= "[{$n}] {$article->title}\n{$desc}\n\n";
        }

        $prompt = "You are selecting news for Latino small business owners in the USA (construction, roofing, HVAC, restaurants, cleaning, landscaping). Pick the 10 most relevant articles from this list. Return ONLY a JSON array of numbers like [1,3,5,7,8,9,10,12,13,14]. No explanation.\n\n{$numbered}";

        $response = $this->callApi($prompt, 256);

        if ($response === null) {
            return [];
        }

        return $this->parseIndexArray($response);
    }

    /**
     * Call Claude with the summarization prompt for a single article.
     * Returns the English summary string, or null on failure.
     */
    private function summarizeArticle(NewsArticle $article): ?string
    {
        $title = $article->title;
        $description = mb_substr($article->description ?? '', 0, 600);

        $prompt = <<<PROMPT
Write a compelling 3-sentence news summary in English for Latino small business owners in the USA. They work in construction, roofing, HVAC, restaurants, cleaning, landscaping. Your summary must: (1) clearly explain what happened, (2) say exactly why it matters to their business or daily life, (3) give a practical takeaway they can act on. Be engaging, warm, and direct — like a trusted advisor talking to a friend. Do not use any markdown formatting. No headers, no bold, no bullet points. Plain text only.

Title: {$title}
Description: {$description}
PROMPT;

        return $this->callApi($prompt, 512);
    }

    /**
     * Call Claude with the Spanish summarization prompt for a single article.
     * Returns the Spanish summary string, or null on failure.
     */
    private function summarizeArticleEs(NewsArticle $article): ?string
    {
        $title = $article->title;
        $description = mb_substr($article->description ?? '', 0, 600);

        $prompt = <<<PROMPT
Escribe un resumen de 3 oraciones en español para dueños de pequeños negocios latinos en USA. Trabajan en construcción, techado, HVAC, restaurantes, limpieza y jardinería. Tu resumen debe: (1) explicar claramente qué pasó, (2) decir exactamente por qué importa para su negocio o vida diaria, (3) dar un paso práctico que puedan tomar. Sé directo y cálido — como un asesor de confianza hablando con un amigo. No uses ningún formato markdown. Sin encabezados, sin negritas, sin viñetas. Solo texto plano.

Title: {$title}
Description: {$description}
PROMPT;

        return $this->callApi($prompt, 512);
    }

    /**
     * Strip common markdown formatting from AI-generated text.
     * Removes headings, bold, and italic markers while preserving the inner text.
     */
    private function stripMarkdown(string $text): string
    {
        // Remove heading lines (e.g. "# Title", "## Title")
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;

        // Remove bold markers (**text** → text)
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;

        // Remove italic markers (*text* → text)
        $text = preg_replace('/\*(.+?)\*/s', '$1', $text) ?? $text;

        return trim($text);
    }

    /**
     * Send a single prompt to the Anthropic Messages API.
     * Returns the response text, or null on HTTP failure.
     */
    private function callApi(string $prompt, int $maxTokens): ?string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('AiNewsService: Anthropic API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('content.0.text', '');
    }

    /**
     * Parse a JSON array of integers from the selection response.
     * E.g. "[1,3,5]" → [1, 3, 5]
     *
     * @return list<int>
     */
    private function parseIndexArray(string $text): array
    {
        // Strip markdown code fences if present
        $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $text) ?? $text;
        $clean = trim($clean);

        $start = strpos($clean, '[');
        $end = strrpos($clean, ']');

        if ($start === false || $end === false) {
            Log::warning('AiNewsService: no JSON array found in selection response', ['raw' => mb_substr($text, 0, 500)]);

            return [];
        }

        $json = substr($clean, $start, $end - $start + 1);

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return [];
            }

            return array_values(array_filter(array_map('intval', $decoded), fn ($v) => $v > 0));
        } catch (\JsonException $e) {
            Log::warning('AiNewsService: JSON parse error in selection response', [
                'error' => $e->getMessage(),
                'json' => mb_substr($json, 0, 500),
            ]);

            return [];
        }
    }
}
