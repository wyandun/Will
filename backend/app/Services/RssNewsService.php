<?php

namespace App\Services;

use App\Enums\NewsArticleStatus;
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
        // Original sources
        'NPR Business' => 'https://feeds.npr.org/1006/rss.xml',
        'New York Times' => 'https://rss.nytimes.com/services/xml/rss/nyt/Business.xml',
        'Fox Business' => 'https://feeds.foxbusiness.com/fox-business/latest',
        'Inc Magazine' => 'https://www.inc.com/rss/',
        'Entrepreneur' => 'https://www.entrepreneur.com/latest.rss',
        'CNBC Small Biz' => 'https://www.cnbc.com/id/10001147/device/rss/rss.html',
        'Reuters Business' => 'https://feeds.reuters.com/reuters/businessNews',
        'NBC News' => 'https://feeds.nbcnews.com/nbcnews/public/news',

        // General business & economy
        'BBC Business' => 'https://feeds.bbci.co.uk/news/business/rss.xml',
        'The Guardian Business' => 'https://www.theguardian.com/us/business/rss',
        'Washington Post Biz' => 'https://feeds.washingtonpost.com/rss/business',
        'USA Today Money' => 'https://rss.usatoday.com/topics/news/money',
        'Fast Company' => 'https://www.fastcompany.com/latest/rss',
        'Forbes Business' => 'https://www.forbes.com/business/feed/',
        'Kiplinger' => 'https://www.kiplinger.com/rss/',
        'Small Biz Trends' => 'https://smallbiztrends.com/feed',
        'Investopedia' => 'https://www.investopedia.com/feedbuilder/feed/getfeed/?feedName=rss_articles',

        // Small business & entrepreneurship
        'SCORE Blog' => 'https://www.score.org/blog/rss.xml',
        'SBA News' => 'https://www.sba.gov/rss/all-news-and-updates.xml',
        'Biz2Credit' => 'https://www.biz2credit.com/blog/feed/',
        'Nav Small Biz' => 'https://www.nav.com/blog/feed/',

        // Tax & regulation
        'IRS Newsroom' => 'https://www.irs.gov/newsroom/irs-news.xml',
        'Tax Foundation' => 'https://taxfoundation.org/feed/',

        // Industry-specific: restaurant, construction, HVAC
        'Restaurant Business' => 'https://www.restaurantbusinessonline.com/rss.xml',
        'Nation Restaurant News' => 'https://www.nrn.com/rss.xml',
        'Construction Dive' => 'https://www.constructiondive.com/feeds/news/',
        'Roofing Contractor' => 'https://www.roofingcontractor.com/rss/',
        'ACHR News HVAC' => 'https://www.achrnews.com/rss/',

        // Labor & immigration
        'DOL News' => 'https://blog.dol.gov/feed',
        'Immigration Impact' => 'https://immigrationimpact.com/feed/',
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

                    // If the article already exists, update image_url when it is null
                    // and the RSS feed provides one (e.g. after the localhost-url cleanup migration).
                    $existing = NewsArticle::where('article_url', $article['url'])->first();

                    if ($existing !== null) {
                        if ($existing->image_url === null && $article['image_url'] !== null) {
                            $existing->update(['image_url' => $article['image_url']]);
                            Log::info("RssNewsService: updated missing image_url for article [{$existing->id}]", [
                                'article_url' => $article['url'],
                                'image_url' => $article['image_url'],
                            ]);
                        }

                        continue;
                    }

                    $newsArticle = NewsArticle::create([
                        'source' => strip_tags($sourceName),
                        'article_url' => $article['url'],
                        'title' => $article['title'],
                        'description' => $article['description'],
                        'image_url' => $article['image_url'], // external URL from RSS — no local caching
                        'published_at' => $article['published_at'],
                        'keywords_matched' => $matched,
                        'status' => NewsArticleStatus::PendingAi->value,
                    ]);

                    $inserted[] = $newsArticle;
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
    public function getPendingForAi(int $limit = 15): Collection
    {
        return NewsArticle::where('status', NewsArticleStatus::PendingAi->value)
            ->orderByDesc('fetched_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Re-queue old rejected articles so they can be re-evaluated.
     *
     * Rejected articles older than $olderThanDays days are reset to pending_ai.
     * This prevents the pool from drying up when RSS feeds recycle the same URLs
     * or when editorial taste changes over time.
     *
     * Returns the number of articles re-queued.
     */
    public function requeueOldRejected(int $olderThanDays = 30): int
    {
        $count = NewsArticle::where('status', NewsArticleStatus::Rejected->value)
            ->where('fetched_at', '<', now()->subDays($olderThanDays))
            ->update(['status' => NewsArticleStatus::PendingAi->value]);

        if ($count > 0) {
            Log::info("RssNewsService: re-queued {$count} old rejected articles for AI re-evaluation");
        }

        return $count;
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    /**
     * Download an RSS/Atom feed and return a normalized array of articles.
     *
     * @return list<array{title: string, url: string, description: string, image_url: string|null, published_at: string|null}>
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

    /** @return array{title: string, url: string, description: string, image_url: string|null, published_at: string|null} */
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
            'image_url' => $this->extractImageUrl($item),
            'published_at' => $pubDate,
        ];
    }

    /** @return array{title: string, url: string, description: string, image_url: string|null, published_at: string|null} */
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
            'image_url' => $this->extractImageUrl($entry),
            'published_at' => $pubDate,
        ];
    }

    /**
     * Try to extract an image URL from an RSS/Atom item element.
     * Checks (in order): media:content, enclosure[image/*], media:thumbnail.
     */
    private function extractImageUrl(\SimpleXMLElement $item): ?string
    {
        // media:content url="..."
        $media = $item->children('media', true);
        if (isset($media->content)) {
            $mediaUrl = (string) ($media->content->attributes()['url'] ?? '');
            if (! empty($mediaUrl) && filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                return $mediaUrl;
            }
        }

        // <enclosure url="..." type="image/...">
        if (isset($item->enclosure)) {
            $encType = (string) ($item->enclosure->attributes()['type'] ?? '');
            $encUrl = (string) ($item->enclosure->attributes()['url'] ?? '');
            if (str_starts_with($encType, 'image/') && ! empty($encUrl) && filter_var($encUrl, FILTER_VALIDATE_URL)) {
                return $encUrl;
            }
        }

        // media:thumbnail url="..."
        if (isset($media->thumbnail)) {
            $thumbUrl = (string) ($media->thumbnail->attributes()['url'] ?? '');
            if (! empty($thumbUrl) && filter_var($thumbUrl, FILTER_VALIDATE_URL)) {
                return $thumbUrl;
            }
        }

        return null;
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
