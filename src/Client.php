<?php

namespace CookielessAudiences;

class Client
{
    const BASE = 'https://www.cookielessaudiences.com';
    const VERSION = '1.0.0';

    const STATUS_TEXT = [
        400 => 'Bad request, check the parameters',
        401 => 'Invalid API key',
        403 => 'Key not active or monthly credits used up',
        407 => 'Missing data_type, must be url or text',
        410 => 'Not enough content in the page or text',
        411 => 'The URL content could not be fetched',
        500 => 'General error, check the request or contact support',
    ];

    /** @var string */
    private $apiKey;
    /** @var int */
    private $timeout;
    /** @var callable|null */
    private $transport;

    /**
     * @param callable|null $transport function (string $method, string $url, array $form): string, for tests
     */
    public function __construct(string $apiKey, int $timeout = 120, ?callable $transport = null)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('An API key is required');
        }
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->transport = $transport;
    }

    /** Page-level audience segmentation. Pass $structured = false for the legacy free-text shape. */
    public function segment(string $url, bool $structured = true): array
    {
        $form = ['query' => $url, 'api_key' => $this->apiKey];
        if ($structured) {
            $form['format'] = 'structured';
        }
        return $this->post('/api/audience/segment.php', $form);
    }

    /** IAB content categorization of a URL. */
    public function categorize(string $url, bool $confidence = true, bool $rootFallback = false): array
    {
        $form = ['query' => $url, 'api_key' => $this->apiKey, 'data_type' => 'url'];
        if ($confidence) {
            $form['confidence'] = '1';
        }
        if ($rootFallback) {
            $form['use_domain_as_basis_of_categorization_for_insufficient_subdomain_content'] = '1';
        }
        return $this->post('/api/iab/iab_web_content_filtering.php', $form);
    }

    /** IAB content categorization of plain text. */
    public function categorizeText(string $text, bool $confidence = true): array
    {
        $form = ['query' => $text, 'api_key' => $this->apiKey, 'data_type' => 'text'];
        if ($confidence) {
            $form['confidence'] = '1';
        }
        return $this->post('/api/iab/iab_content_filtering.php', $form);
    }

    /** Public vocabularies, no API key needed. */
    public function vocabularies(): array
    {
        return $this->decode($this->send('GET', self::BASE . '/api/audience/filters.php', []));
    }

    /** Readable names for the INT.* and PI.* codes of a structured response. */
    public static function labelsFor(array $result): array
    {
        $names = $result['labels'] ?? [];
        $out = [];
        foreach (['interests', 'purchase_intent'] as $group) {
            foreach (['tier1', 'tier2', 'codes'] as $key) {
                foreach ($result[$group][$key] ?? [] as $code) {
                    $out[] = $names[$code] ?? $code;
                }
            }
        }
        return $out;
    }

    private function post(string $path, array $form): array
    {
        $body = $this->decode($this->send('POST', self::BASE . $path, $form));
        $status = isset($body['status']) ? (int) $body['status'] : 200;
        if ($status !== 200) {
            throw new CookielessAudiencesException($status, self::STATUS_TEXT[$status] ?? 'API error', $body);
        }
        return $body;
    }

    private function decode(string $raw): array
    {
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new CookielessAudiencesException(0, 'Response was not a JSON object');
        }
        return $json;
    }

    private function send(string $method, string $url, array $form): string
    {
        if ($this->transport !== null) {
            return (string) call_user_func($this->transport, $method, $url, $form);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'cookielessaudiences-php/' . self::VERSION . ' (+https://www.cookielessaudiences.com)',
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new CookielessAudiencesException(0, 'Transport error: ' . $err);
        }
        curl_close($ch);
        return (string) $raw;
    }
}
