<?php

namespace CapsuleCmdr\SeatOsmm\Support\Esi;

// import interfaces you just created
use CapsuleCmdr\SeatOsmm\Support\Esi\EsiTokenStorage;
use CapsuleCmdr\SeatOsmm\Support\Esi\LaravelDbEsiTokenStorage;

class EsiCall
{
    // Config
    protected string $base = 'https://esi.evetech.net/latest';
    protected string $datasource = 'tranquility'; // or 'singularity'
    protected string $userAgent = 'CapsuleCmdr-OSMM/1.0 (+https://capsulecmd.com)';

    // Request
    protected string $endpoint;
    protected string $method = 'GET';
    protected array  $path_params = [];
    protected array  $query = [];
    protected ?string $body = null;
    protected ?string $bodyContentType = null; // 'application/json', 'application/x-www-form-urlencoded', 'text/plain', etc.
    protected ?string $bearer = null;
    protected ?string $ifNoneMatch = null;
    protected ?string $ifModifiedSince = null;
    protected array  $extraHeaders = [];
    protected int    $timeout = 20;
    protected int    $connectTimeout = 5;

    // Behavior
    protected bool $assoc = true;
    protected int  $maxRetries = 2;       // total attempts = 1 + maxRetries
    protected int  $retryBaseMs = 400;    // backoff base
    protected int  $retryJitterMs = 200;  // +/- jitter

    // Response
    protected ?int   $status = null;
    protected array  $headers = [];
    protected $data = null;
    protected ?array $err = null;
    protected ?string $raw = null;

    public static function make(string $endpoint): self {
        $self = new self();
        $self->endpoint = $endpoint;
        return $self;
    }

    // ---- Configuration / request building ----------------------------------

    public function base(string $base): self { $this->base = rtrim($base, '/'); return $this; }
    public function datasource(string $ds): self { $this->datasource = $ds; return $this; }
    public function userAgent(string $ua): self { $this->userAgent = $ua; return $this; }

    public function method(string $method): self { $this->method = strtoupper($method); return $this; }
    public function get(): self { return $this->method('GET'); }
    public function post(): self { return $this->method('POST'); }
    public function put(): self { return $this->method('PUT'); }
    public function delete(): self { return $this->method('DELETE'); }
    public function head(): self { return $this->method('HEAD'); }
    public function options(): self { return $this->method('OPTIONS'); }

    public function pathParams(array $params): self { $this->path_params = $params; return $this; }
    public function query(array $query): self { $this->query = $query + $this->query; return $this; }

    // Bodies
    public function jsonBody($payload): self {
        $this->body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $this->bodyContentType = 'application/json';
        return $this;
    }
    public function formBody(array $fields): self {
        $this->body = http_build_query($fields);
        $this->bodyContentType = 'application/x-www-form-urlencoded';
        return $this;
    }
    public function rawBody(string $raw, string $contentType = 'text/plain'): self {
        $this->body = $raw;
        $this->bodyContentType = $contentType;
        return $this;
    }

    // Auth + caching headers
    public function bearer(?string $token): self { $this->bearer = $token; return $this; }
    public function ifNoneMatch(string $etag): self { $this->ifNoneMatch = $etag; return $this; }
    public function ifModifiedSince(string $httpDate): self { $this->ifModifiedSince = $httpDate; return $this; }

    // Misc
    public function headers(array $headers): self { $this->extraHeaders = $headers + $this->extraHeaders; return $this; }
    public function timeout(int $seconds): self { $this->timeout = max(1, $seconds); return $this; }
    public function connectTimeout(int $seconds): self { $this->connectTimeout = max(1, $seconds); return $this; }
    public function asAssoc(bool $assoc = true): self { $this->assoc = $assoc; return $this; }
    public function retries(int $maxRetries, int $baseMs = 400, int $jitterMs = 200): self {
        $this->maxRetries = max(0, $maxRetries);
        $this->retryBaseMs = max(0, $baseMs);
        $this->retryJitterMs = max(0, $jitterMs);
        return $this;
    }

    /**
     * Helper: resolve and attach a valid Bearer token from Auth::user()
     * (works with Laravel + your storage implementation).
     */
    public function withSeatUser($user, ?int $characterId = null, ?EsiTokenStorage $storage = null): self {
        $storage ??= new LaravelDbEsiTokenStorage();
        $charIds = $storage->listCharacterIdsForUser($user);
        if (empty($charIds)) { $this->err = ['code' => 0, 'message' => 'No characters linked to user.']; return $this; }
        if ($characterId === null) {
            if (count($charIds) !== 1) { $this->err = ['code' => 0, 'message' => 'Multiple characters found; specify $characterId.']; return $this; }
            $characterId = $charIds[0];
        }
        $tok = $storage->getTokenFor($characterId);
        if (!$tok) { $this->err = ['code' => 0, 'message' => "No token found for character {$characterId}."]; return $this; }
        if (!empty($tok['refresh_token']) && ($tok['access_token'] === null || time() >= (int)$tok['expires_at'] - 60)) {
            $refreshed = $this->refreshAccessToken($tok['refresh_token']);
            if ($refreshed) {
                $tok['access_token']  = $refreshed['access_token'];
                $tok['refresh_token'] = $refreshed['refresh_token'] ?? $tok['refresh_token'];
                $tok['expires_at']    = time() + (int)($refreshed['expires_in'] ?? 1200);
                $tok['scopes']        = $refreshed['scope'] ?? ($tok['scopes'] ?? '');
                $storage->saveToken($characterId, $tok);
            }
        }
        $this->bearer($tok['access_token'] ?? null);
        return $this;
    }

    // ---- Execution ----------------------------------------------------------

    public function run(): self {
        $this->resetResult();

        // Ensure datasource is present unless caller provided one
        if (!isset($this->query['datasource'])) {
            $this->query['datasource'] = $this->datasource;
        }

        $attempts = 0;
        $maxAttempts = 1 + $this->maxRetries;

        do {
            $attempts++;
            $this->singleRequest();

            if ($this->shouldRetry()) {
                $this->sleepBackoff($attempts);
            } else {
                break;
            }
        } while ($attempts < $maxAttempts);

        return $this;
    }

    /**
     * Auto-paginate endpoints that return X-Pages. Aggregates pages into one array.
     * Only for JSON-array responses. If you need to transform each page, pass $merge as a callable.
     */
    public function autoPaginate(?callable $merge = null, int $startPage = 1, int $maxPages = 100): self
    {
        $this->query['page'] = $startPage;
        $this->run();
        if (!$this->ok()) return $this;

        $pages = (int)($this->header('X-Pages') ?? 1);
        $pages = min($pages, $maxPages);

        $all = [];
        $firstData = $this->data();
        if (is_array($firstData)) $all = $firstData;

        for ($p = $startPage + 1; $p <= $pages; $p++) {
            $pageCall = clone $this;
            $pageCall->query(['page' => $p]);
            $pageCall->run();
            if (!$pageCall->ok()) { $this->err = $pageCall->error(); break; }
            $d = $pageCall->data();
            if (is_array($d)) {
                if ($merge) { $all = $merge($all, $d); }
                else { $all = array_merge($all, $d); }
            }
            // carry forward last page's meta
            $this->status  = $pageCall->status();
            $this->headers = $pageCall->headersAll();
        }

        $this->data = $all;
        return $this;
    }

    // ---- Results API --------------------------------------------------------

    public function ok(): bool {
        return $this->err === null && $this->status !== null && $this->status >= 200 && $this->status < 300;
    }
    public function status(): ?int { return $this->status; }
    public function data() { return $this->data; }
    public function json(int $flags = 0): ?string { return $this->data === null ? null : json_encode($this->data, $flags | JSON_UNESCAPED_SLASHES); }
    public function headersAll(): array { return $this->headers; }
    public function header(string $name, $default = null) {
        foreach ($this->headers as $k => $v) if (strcasecmp($k, $name) === 0) return $v;
        return $default;
    }
    public function etag(): ?string { return $this->header('ETag'); }
    public function error(): ?array { return $this->err; }
    public function raw(): ?string { return $this->raw; }

    // ---- Internals ----------------------------------------------------------

    protected function singleRequest(): void
    {
        $this->raw = null;
        $this->err = null;
        $this->status = null;
        $this->headers = [];

        $url = $this->buildUrl();
        $ch = curl_init($url);

        $reqHeaders = [
            'Accept: application/json',
            'User-Agent: ' . $this->userAgent,
        ];
        if ($this->bearer)          $reqHeaders[] = 'Authorization: Bearer ' . $this->bearer;
        if ($this->ifNoneMatch)     $reqHeaders[] = 'If-None-Match: ' . $this->ifNoneMatch;
        if ($this->ifModifiedSince) $reqHeaders[] = 'If-Modified-Since: ' . $this->ifModifiedSince;
        if ($this->body !== null && $this->bodyContentType) $reqHeaders[] = 'Content-Type: ' . $this->bodyContentType;
        foreach ($this->extraHeaders as $k => $v) $reqHeaders[] = $k . ': ' . $v;

        // Method
        switch ($this->method) {
            case 'GET':     curl_setopt($ch, CURLOPT_HTTPGET, true); break;
            case 'POST':    curl_setopt($ch, CURLOPT_POST, true);    break;
            case 'PUT':
            case 'DELETE':
            case 'HEAD':
            case 'OPTIONS': curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method); break;
            default:        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method); break;
        }
        if ($this->body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
        if ($this->method === 'HEAD') curl_setopt($ch, CURLOPT_NOBODY, true);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) {
                $len = strlen($line);
                $this->parseHeaderLine($line);
                return $len;
            },
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER     => $reqHeaders,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $this->status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: null;
        curl_close($ch);

        if ($errno) {
            $this->err = ['code' => $errno, 'message' => $error];
            return;
        }

        $this->raw = is_string($body) ? $body : null;

        // HEAD/204/304 → no body expected
        if ($this->method === 'HEAD' || $this->status === 204 || $this->status === 304 || $body === '' || $body === null) {
            $this->data = null;
            return;
        }

        // Try JSON first, else store raw text
        $decoded = json_decode($body, $this->assoc);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->data = $decoded;
        } else {
            $this->data = $this->assoc ? ['raw' => $body] : (object)['raw' => $body];
        }

        if ($this->status >= 400) {
            $msg = 'HTTP error';
            if (is_array($this->data) && isset($this->data['error'])) $msg = $this->data['error'];
            if (is_object($this->data) && isset($this->data->error))   $msg = $this->data->error;
            $this->err = ['code' => $this->status, 'message' => $msg, 'body' => $this->raw];
        }
    }

    protected function shouldRetry(): bool
    {
        if ($this->err && isset($this->err['code']) && $this->err['code'] === CURLE_OPERATION_TIMEDOUT) return true;
        if ($this->status === null) return false;

        // ESI rate limit/exhaustion or transient server errors
        if ($this->status == 420) return true; // ESI "Enhance your calm"
        if ($this->status >= 500 && $this->status <= 599) return true;

        return false;
    }

    protected function sleepBackoff(int $attempt): void
    {
        $ms = $this->retryBaseMs * (2 ** ($attempt - 1));
        $j  = random_int(-$this->retryJitterMs, $this->retryJitterMs);
        usleep(max(0, ($ms + $j)) * 1000);
    }

    protected function resetResult(): void
    {
        $this->status = null;
        $this->headers = [];
        $this->data = null;
        $this->err = null;
        $this->raw = null;
    }

    protected function buildUrl(): string
    {
        $path = $this->endpoint;
        foreach ($this->path_params as $k => $v) {
            $path = str_replace('{' . $k . '}', rawurlencode((string)$v), $path);
        }
        $url = rtrim($this->base, '/') . '/' . ltrim($path, '/');

        if (!empty($this->query)) {
            $parts = [];
            foreach ($this->query as $k => $val) {
                if (is_array($val)) {
                    foreach ($val as $vv) $parts[] = urlencode($k) . '=' . urlencode((string)$vv);
                } else {
                    $parts[] = urlencode($k) . '=' . urlencode((string)$val);
                }
            }
            $url .= '?' . implode('&', $parts);
        }
        return $url;
    }

    protected function parseHeaderLine(string $line): void
    {
        $trim = trim($line);
        if ($trim === '' || stripos($trim, 'HTTP/') === 0) return;
        $pos = strpos($trim, ':');
        if ($pos === false) return;
        $name  = trim(substr($trim, 0, $pos));
        $value = trim(substr($trim, $pos + 1));
        if (!array_key_exists($name, $this->headers)) $this->headers[$name] = $value;
    }

    protected function refreshAccessToken(string $refreshToken): ?array
    {
        $clientId     = env('EVE_CLIENT_ID');
        $clientSecret = env('EVE_CLIENT_SECRET');
        if (!$clientId || !$clientSecret) return null;

        $url  = 'https://login.eveonline.com/v2/oauth/token';
        $auth = base64_encode($clientId . ':' . $clientSecret);

        $payload = http_build_query([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $auth,
                'Host: login.eveonline.com',
                'User-Agent: ' . $this->userAgent,
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $resp  = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno || !$resp) return null;
        $json = json_decode($resp, true);
        return (is_array($json) && !empty($json['access_token'])) ? $json : null;
    }
}