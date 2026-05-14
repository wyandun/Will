<?php

namespace App\Services;

use App\Models\NewsArticle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RssNewsService
{
    /**
     * RSS feed sources — all free, no API key required.
     *
     * @var array<string, string>
     */
    private const SOURCES = [
        'NPR Business' => 'https://feeds.npr.org/1006/rss.xml',
        'New York Times' => 'https://rss.nytimes.com/services/xml/rss/nyt/Business.xml',
        'Fox Business' => 'https://feeds.foxbusiness.com/fox-business/latest',
        'Inc Magazine' => 'https://www.inc.com/rss/',
        'Entrepreneur' => 'https://www.entrepreneur.com/latest.rss',
        'CNBC Small Biz' => 'https://www.cnbc.com/id/10001147/device/rss/rss.html',
        'Reuters Business' => 'https://feeds.reuters.com/reuters/businessNews',
        'NBC News' => 'https://feeds.nbcnews.com/nbcnews/public/news',
    ];

    /**
     * Keywords relevant to Latino small business owners in the USA.
     *
     * @var list<string>
     */
    private const KEYWORDS = [
        'small business', 'franchise', 'franquicia',
        'restaurant', 'construction', 'contractor',
        'tax', 'taxes', 'irs',
        'labor', 'employment', 'minimum wage',
        'immigration', 'visa', 'work permit',
        'loan', 'sba', 'financing',
        'insurance', 'permit', 'license',
        'supply chain', 'inflation', 'tariff',
        'hispanic', 'latino', 'minority business',
        'entrepreneur', 'startup',
    ];

    /**
     * Fetch articles from all RSS sources, filter by keywords, and persist new ones.
     * Returns the list of newly inserted articles.
     *
     * @return list<NewsArticle>
     */
    public function fetchAndFilter(): array
    {
        $inserted = [];

        foreach (self::SOURCES as $sourceName => $feedUrl) {
            try {
                $articles = $this->parseFeed($sourceName, $feedUrl);

                foreach ($articles as $article) {
                    $matched = $this->matchKeywords($article['title'], $article['description']);

                    if (empty($matched)) {
                        continue;
                    }

                    // Skip already-known URLs
                    if (NewsArticle::where('article_url', $article['url'])->exists()) {
                        continue;
                    }

                    $inserted[] = NewsArticle::create([
                        'source' => $sourceName,
                        'article_url' => $article['url'],
                        'title' => $article['title'],
                        'description' => $article['description'],
                        'published_at' => $article['published_at'],
                        'keywords_matched' => $matched,
                        'status' => 'pending_ai',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("RssNewsService: failed to parse feed [{$sourceName}]", [
                    'url' => $feedUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $inserted;
    }

    /**
     * Retrieve up to $limit pending_ai articles for AI processing.
     *
     * @return Collection<int, NewsArticle>
     */
    public function getPendingForAi(int $limit = 15)
    {
        return NewsArticle::where('status', 'pending_ai')
            ->orderByDesc('fetched_at')
            ->limit($limit)
            ->get();
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    /**
     * Download an RSS/Atom feed and return a normalized array of articles.
     *
     * @return list<array{title: string, url: string, description: string, published_at: string|null}>
     */
    private function parseFeed(string $source, string $url): array
    {
        $response = Http::timeout(10)->get($url);

        if (! $response->successful()) {
            return [];
        }

        // Suppress libxml warnings for malformed feeds
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body());
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            return [];
        }

        $articles = [];

        // RSS 2.0
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $articles[] = $this->normalizeItem($item);
            }
        }
        // Atom
        elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $articles[] = $this->normalizeAtomEntry($entry);
            }
        }

        return array_filter($articles, fn ($a) => ! empty($a['url']));
    }

    /** @return array{title: string, url: string, description: string, published_at: string|null} */
    private function normalizeItem(\SimpleXMLElement $item): array
    {
        $url = (string) ($item->link ?? '');

        // Some feeds use <guid isPermaLink="true"> as the canonical URL
        if (empty($url) && isset($item->guid) && filter_var((string) $item->guid, FILTER_VALIDATE_URL)) {
            $url = (string) $item->guid;
        }

        $pubDate = isset($item->pubDate) ? date('Y-m-d H:i:s', strtotime((string) $item->pubDate)) : null;

        return [
            'title' => html_entity_decode(strip_tags((string) ($item->title ?? '')), ENT_QUOTES, 'UTF-8'),
            'url' => $url,
            'description' => html_entity_decode(strip_tags((string) ($item->description ?? '')), ENT_QUOTES, 'UTF-8'),
            'published_at' => $pubDate,
        ];
    }

    /** @return array{title: string, url: string, description: string, published_at: string|null} */
    private function normalizeAtomEntry(\SimpleXMLElement $entry): array
    {
        $url = '';
        if (isset($entry->link)) {
            foreach ($entry->link as $link) {
                $rel = (string) ($link['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $rel === '') {
                    $url = (string) ($link['href'] ?? '');
                    break;
                }
            }
        }

        $pubDate = null;
        if (isset($entry->published)) {
            $pubDate = date('Y-m-d H:i:s', strtotime((string) $entry->published));
        } elseif (isset($entry->updated)) {
            $pubDate = date('Y-m-d H:i:s', strtotime((string) $entry->updated));
        }

        return [
            'title' => html_entity_decode(strip_tags((string) ($entry->title ?? '')), ENT_QUOTES, 'UTF-8'),
            'url' => $url,
            'description' => html_entity_decode(strip_tags((string) ($entry->summary ?? $entry->content ?? '')), ENT_QUOTES, 'UTF-8'),
            'published_at' => $pubDate,
        ];
    }

    /**
     * Returns the list of matched keywords (case-insensitive) found in title+description.
     *
     * @return list<string>
     */
    private function matchKeywords(string $title, string $description): array
    {
        $haystack = mb_strtolower($title.' '.$description);
        $matched = [];

        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($haystack, mb_strtolower($keyword))) {
                $matched[] = $keyword;
            }
        }

        return array_values(array_unique($matched));
    }
}
