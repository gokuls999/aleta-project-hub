<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/**
 * Thin Zoho Projects API v3 client.
 * - Refreshes and caches the access token in the settings table.
 * - Provides get/post/put/delete plus the handful of endpoints this app uses.
 */
class Zoho
{
    private string $api;
    private string $accounts;
    private string $portal;

    public function __construct()
    {
        $this->api      = rtrim(cfg('zoho.api_base'), '/');
        $this->accounts = rtrim(cfg('zoho.accounts_base'), '/');
        $this->portal   = (string)cfg('zoho.portal_id');
    }

    /** Valid access token, refreshed if needed (cached in settings). */
    private function token(): string
    {
        $cached = setting_get('zoho_access_token');
        $exp    = (int)setting_get('zoho_access_expiry', '0');
        if ($cached && time() < $exp - 60) return $cached;

        $params = http_build_query([
            'refresh_token' => cfg('zoho.refresh_token'),
            'client_id'     => cfg('zoho.client_id'),
            'client_secret' => cfg('zoho.client_secret'),
            'grant_type'    => 'refresh_token',
        ]);
        $resp = $this->raw('POST', "{$this->accounts}/oauth/v2/token?{$params}", null, false);
        $j = json_decode($resp, true);
        if (empty($j['access_token'])) {
            throw new RuntimeException('Zoho token refresh failed: ' . substr($resp, 0, 200));
        }
        setting_set('zoho_access_token', $j['access_token']);
        setting_set('zoho_access_expiry', (string)(time() + (int)($j['expires_in'] ?? 3600)));
        return $j['access_token'];
    }

    /** Low-level curl. $auth adds the OAuth header. */
    private function raw(string $method, string $url, $body = null, bool $auth = true): string
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($auth) $headers[] = 'Authorization: Zoho-oauthtoken ' . $this->token();
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 30,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $out = curl_exec($ch);
        if ($out === false) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException("curl: $err"); }
        curl_close($ch);
        return $out;
    }

    /** Authenticated JSON request against an /api/v3 path (leading slash). */
    public function request(string $method, string $path, $body = null): array
    {
        $url = $this->api . $path;
        $raw = $this->raw($method, $url, $body, true);
        $j = json_decode($raw, true);
        if ($j === null && trim($raw) !== '') {
            throw new RuntimeException("Zoho non-JSON ($method $path): " . substr($raw, 0, 200));
        }
        return $j ?? [];
    }

    // ---- endpoints this app uses -------------------------------------------

    public function portalPath(string $suffix = ''): string
    {
        return "/portal/{$this->portal}{$suffix}";
    }

    /** List active projects (paginated). */
    public function getProjects(int $page = 1, int $perPage = 100): array
    {
        $r = $this->request('GET', $this->portalPath("/projects?page={$page}&per_page={$perPage}"));
        return $r['data'] ?? $r['result'] ?? $r ?? [];
    }

    /** Tasks in a project. */
    public function getTasks(string $projectId, int $page = 1, int $perPage = 100): array
    {
        $r = $this->request('GET', $this->portalPath("/projects/{$projectId}/tasks?page={$page}&per_page={$perPage}"));
        return $r['tasks'] ?? $r['data'] ?? [];
    }

    /** Portal users (staff), for mapping assignees to app accounts. */
    public function getUsers(): array
    {
        $r = $this->request('GET', $this->portalPath('/users?per_page=200'));
        return $r['users'] ?? $r['data'] ?? [];
    }

    /** Task lists in a project. */
    public function getTaskLists(string $projectId, int $page = 1, int $perPage = 100): array
    {
        $r = $this->request('GET', $this->portalPath("/projects/{$projectId}/tasklists?page={$page}&per_page={$perPage}"));
        return $r['tasklists'] ?? $r['data'] ?? [];
    }

    /** Create a task. $fields per Zoho v3 (name, tasklist_id, etc.). */
    public function createTask(string $projectId, array $fields): array
    {
        return $this->request('POST', $this->portalPath("/projects/{$projectId}/tasks"), $fields);
    }

    /** Update a task. */
    public function updateTask(string $projectId, string $taskId, array $fields): array
    {
        return $this->request('PUT', $this->portalPath("/projects/{$projectId}/tasks/{$taskId}"), $fields);
    }
}
