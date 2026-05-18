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
        $this->apiKey = config('services.anthropic.api_key')
            ?? throw new \RuntimeException('services.anthropic.api_key is not configured');

        $this->model = config('services.anthropic.model')
            ?? throw new \RuntimeException('services.anthropic.model is not configured');
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
                    'status' => NewsArticleStatus::Rejected,
                ]);

                continue;
            }

            $summary = $this->summarizeArticle($article);
            $spanishResult = $this->summarizeArticleEs($article);

            $article->update([
                'ai_summary' => $summary !== null ? $this->stripMarkdown($summary) : null,
                'ai_summary_es' => $spanishResult['summary'] !== null ? $this->stripMarkdown($spanishResult['summary']) : null,
                'title_es' => $spanishResult['title'] !== null ? $this->stripMarkdown($spanishResult['title']) : null,
                'ai_selected' => true,
                'status' => NewsArticleStatus::PendingReview,
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

        $prompt = "You are selecting news for Latino small business owners in the USA (construction, roofing, HVAC, restaurants, cleaning, landscaping). Pick the 15 most relevant articles from this list. Return ONLY a JSON array of numbers like [1,3,5,7,8,9,10,12,13,14,15,16,17,18,19]. No explanation.\n\n{$numbered}";

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
Write a 3-sentence news summary in English for Latino small business owners in the USA (construction, roofing, HVAC, restaurants, cleaning, landscaping). Your summary must: (1) clearly explain what happened, (2) say exactly why it matters to their business or daily life, (3) give one practical takeaway they can act on. Do NOT start with any preamble or introduction. Do NOT use "Here's a summary", "This article", or similar openers. Start directly with the content. Plain text only — no markdown, no headers, no bold.

Title: {$title}
Description: {$description}
PROMPT;

        return $this->callApi($prompt, 512);
    }

    /**
     * Call Claude with the Spanish summarization prompt for a single article.
     * Returns an array with 'title' (translated title) and 'summary' (3-sentence summary),
     * both in Spanish. Either value may be null on failure.
     *
     * @return array{title: string|null, summary: string|null}
     */
    private function summarizeArticleEs(NewsArticle $article): array
    {
        $title = $article->title;
        $description = mb_substr($article->description ?? '', 0, 600);

        $prompt = <<<PROMPT
Eres un asistente para dueños de pequeños negocios latinos en USA (construcción, techado, HVAC, restaurantes, limpieza, jardinería).

Dado el artículo de noticias en inglés, responde ÚNICAMENTE con un objeto JSON válido con exactamente estas dos claves:
- "title_es": traducción al español del título original, fiel y natural (máximo 120 caracteres)
- "summary_es": resumen de 3 oraciones en español. Debe: (1) explicar claramente qué pasó, (2) decir exactamente por qué importa para su negocio o vida diaria, (3) dar un paso práctico que puedan tomar. NO uses introducciones ni saludos. NO empieces con "Hermano", "Mira", "Aquí" ni nada similar. Empieza directo con el contenido. Sin markdown, sin encabezados, sin negritas. Solo texto plano.

Responde SOLO con el JSON, sin texto adicional, sin bloques de código.

Title: {$title}
Description: {$description}
PROMPT;

        $raw = $this->callApi($prompt, 768);

        if ($raw === null) {
            return ['title' => null, 'summary' => null];
        }

        return $this->parseSpanishResult($raw);
    }

    /**
     * Parse the JSON response from the Spanish summarization prompt.
     * Returns an array with 'title' and 'summary' keys.
     *
     * @return array{title: string|null, summary: string|null}
     */
    private function parseSpanishResult(string $raw): array
    {
        // Strip markdown code fences if present
        $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $raw) ?? $raw;
        $clean = trim($clean);

        try {
            $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                Log::warning('AiNewsService: Spanish result is not a JSON object', ['raw' => mb_substr($raw, 0, 500)]);

                return ['title' => null, 'summary' => null];
            }

            return [
                'title' => isset($decoded['title_es']) && is_string($decoded['title_es'])
                    ? $decoded['title_es']
                    : null,
                'summary' => isset($decoded['summary_es']) && is_string($decoded['summary_es'])
                    ? $decoded['summary_es']
                    : null,
            ];
        } catch (\JsonException $e) {
            Log::warning('AiNewsService: JSON parse error in Spanish result', [
                'error' => $e->getMessage(),
                'raw' => mb_substr($raw, 0, 500),
            ]);

            return ['title' => null, 'summary' => null];
        }
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
