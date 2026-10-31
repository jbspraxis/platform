<?php

namespace App\Support\Google;

use App\Support\Integrations;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * HQ's Google connection: praxisjbs@gmail.com's Calendar, Meet and Docs (docs/OPPORTUNITIES.md 6.1 and 8.2).
 * Resolve it from the container (app(GoogleWorkspace::class)) so tests can swap in Tests\Support\Google\FakeGoogle.
 *
 * The OAuth client (ID and secret) and the refresh token live in integration_settings (encrypted); the access
 * token is cached encrypted until shortly before it expires. When Google answers invalid_grant the connection is
 * marked revoked: System health turns red and every call throws GoogleUnavailable('revoked') until the owner
 * reconnects. Nothing here logs a token, a transcript or an attendee.
 */
class GoogleWorkspace
{
    public const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.freebusy',
        'https://www.googleapis.com/auth/meetings.space.readonly',
        'https://www.googleapis.com/auth/documents.readonly',
    ];

    /** WAT: Lagos and Douala share it */
    public const TIME_ZONE = 'Africa/Lagos';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const CALENDAR = 'https://www.googleapis.com/calendar/v3';
    private const MEET = 'https://meet.googleapis.com/v2';
    private const DOCS = 'https://docs.googleapis.com/v1';

    private const TOKEN_CACHE = 'integrations:google:access_token';

    // ---- Connection --------------------------------------------------------------------------------------

    /** The redirect URI registered on the OAuth client: https://hq.jbspraxis.com/integrations/google/callback on live */
    public static function redirectUri(): string
    {
        return route('hq.integrations.google.callback');
    }

    public function hasClient(): bool
    {
        return Integrations::get('google.client_id') !== null && Integrations::get('google.client_secret') !== null;
    }

    public function isConnected(): bool
    {
        return $this->hasClient() && Integrations::get('google.refresh_token') !== null && ! $this->needsReconnect();
    }

    /** Google withdrew access (invalid_grant): the owner must click Connect Google again */
    public function needsReconnect(): bool
    {
        return Integrations::get('google.status') === 'revoked';
    }

    public function accountEmail(): ?string
    {
        return Integrations::get('google.account_email');
    }

    /** @return list<string> */
    public function grantedScopes(): array
    {
        return array_values(array_filter(explode(' ', (string) Integrations::get('google.scopes'))));
    }

    /** @return list<string> scopes HQ needs that the connection lacks */
    public function missingScopes(): array
    {
        $granted = $this->grantedScopes();
        // Google reports openid and email as their long names
        $aliases = ['email' => 'https://www.googleapis.com/auth/userinfo.email'];

        return array_values(array_filter(self::SCOPES, fn ($s) => ! in_array($s, $granted, true) && ! in_array($aliases[$s] ?? '', $granted, true)));
    }

    /** Where Connect Google sends the owner (offline access, consent every time so Google returns a refresh token) */
    public function authorizationUrl(string $state): string
    {
        if (! $this->hasClient()) {
            throw new GoogleUnavailable('not_connected');
        }

        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => Integrations::get('google.client_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Exchange the code from the callback; stores the refresh token, the account's email and the scopes */
    public function connect(string $code): string
    {
        $token = $this->tokenRequest(['code' => $code, 'redirect_uri' => self::redirectUri(), 'grant_type' => 'authorization_code']);
        if (empty($token['refresh_token'])) {
            // Google only sends one with prompt=consent; without it HQ can't work once the hour is up
            throw new GoogleUnavailable('failed');
        }

        $email = $this->emailFromIdToken($token['id_token'] ?? null);
        Integrations::set('google.refresh_token', $token['refresh_token']);
        Integrations::set('google.account_email', $email);
        Integrations::set('google.scopes', (string) ($token['scope'] ?? ''));
        Integrations::set('google.status', 'connected');
        $this->cacheAccessToken($token);

        return (string) $email;
    }

    /** Revoke at Google (best effort) and forget the connection; the client ID and secret stay */
    public function disconnect(): void
    {
        $refresh = Integrations::get('google.refresh_token');
        if ($refresh !== null) {
            try {
                Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $refresh]);
            } catch (Throwable) {
                // Already revoked or Google unreachable: forgetting it here is what matters
            }
        }
        foreach (['google.refresh_token', 'google.account_email', 'google.scopes', 'google.status'] as $key) {
            Integrations::set($key, null);
        }
        Cache::forget(self::TOKEN_CACHE);
    }

    /** A harmless call to prove the connection works: the company calendar's free/busy for the next hour */
    public function test(): string
    {
        $this->freeBusy(['primary'], CarbonImmutable::now(), CarbonImmutable::now()->addHour());

        return (string) $this->accountEmail();
    }

    // ---- Calendar and Meet --------------------------------------------------------------------------------

    /** Create the event with a Meet link in the company calendar; Google sends the invitations */
    public function createMeeting(MeetingRequest $meeting): CalendarEvent
    {
        $event = $this->call('post', self::CALENDAR . '/calendars/primary/events', [
            'conferenceDataVersion' => 1,
            'sendUpdates' => $meeting->notify ? 'all' : 'none',
        ], $meeting->toEvent() + [
            'conferenceData' => ['createRequest' => [
                'requestId' => (string) Str::uuid(),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ]],
        ]);

        // Google may still be creating the Meet space: read the event again for its link
        for ($i = 0; $i < 3 && ($event['conferenceData']['createRequest']['status']['statusCode'] ?? 'success') === 'pending'; $i++) {
            usleep(800_000);
            $event = $this->call('get', self::CALENDAR . '/calendars/primary/events/' . rawurlencode($event['id']));
        }

        return CalendarEvent::fromGoogle($event);
    }

    /** Move or change the event; Google tells the attendees */
    public function updateMeeting(string $eventId, MeetingRequest $meeting): CalendarEvent
    {
        $event = $this->call('patch', self::CALENDAR . '/calendars/primary/events/' . rawurlencode($eventId), [
            'conferenceDataVersion' => 1,
            'sendUpdates' => $meeting->notify ? 'all' : 'none',
        ], $meeting->toEvent());

        return CalendarEvent::fromGoogle($event);
    }

    /** Cancel the event; Google tells the attendees. An event already gone counts as cancelled. */
    public function cancelMeeting(string $eventId, bool $notify = true): void
    {
        try {
            $this->call('delete', self::CALENDAR . '/calendars/primary/events/' . rawurlencode($eventId), ['sendUpdates' => $notify ? 'all' : 'none']);
        } catch (GoogleUnavailable $e) {
            if ($e->reason !== 'not_found') {
                throw $e;
            }
        }
    }

    /**
     * Busy times per calendar between $from and $to. Calendars Google can't read (not shared with
     * praxisjbs@gmail.com) come back as null, so the caller can fall back to working hours only.
     *
     * @param  list<string>  $emails  calendar ids: 'primary' or an email address
     * @return array<string, list<array{start: CarbonImmutable, end: CarbonImmutable}>|null>
     */
    public function freeBusy(array $emails, CarbonInterface $from, CarbonInterface $to): array
    {
        $result = $this->call('post', self::CALENDAR . '/freeBusy', [], [
            'timeMin' => $from->copy()->utc()->toRfc3339String(),
            'timeMax' => $to->copy()->utc()->toRfc3339String(),
            'timeZone' => 'UTC',
            'items' => array_map(fn ($e) => ['id' => $e], array_values($emails)),
        ]);

        $out = [];
        foreach ($emails as $email) {
            $cal = $result['calendars'][$email] ?? null;
            $out[$email] = $cal === null || ! empty($cal['errors']) ? null : array_map(fn ($b) => [
                'start' => CarbonImmutable::parse($b['start'])->utc(),
                'end' => CarbonImmutable::parse($b['end'])->utc(),
            ], $cal['busy'] ?? []);
        }

        return $out;
    }

    /**
     * Route 1 of the transcript collector (6.1): Meet's own transcript of the conference held with this code
     * between the two times, line by line with the speakers' names. Null while Google is still writing it,
     * or when the meeting had no transcript.
     */
    public function conferenceTranscript(string $meetCode, CarbonInterface $startedAfter, CarbonInterface $endedBefore): ?Transcript
    {
        $records = $this->call('get', self::MEET . '/conferenceRecords', [
            'filter' => sprintf('space.meeting_code = "%s" AND start_time >= "%s" AND start_time <= "%s"',
                strtolower($meetCode), $startedAfter->copy()->utc()->format('Y-m-d\TH:i:s\Z'), $endedBefore->copy()->utc()->format('Y-m-d\TH:i:s\Z')),
        ])['conferenceRecords'] ?? [];
        usort($records, fn ($a, $b) => strcmp($a['startTime'] ?? '', $b['startTime'] ?? ''));

        $entries = [];
        $languages = [];
        $docId = null;
        foreach ($records as $record) {
            $transcripts = array_filter(
                $this->call('get', self::MEET . '/' . $record['name'] . '/transcripts')['transcripts'] ?? [],
                fn ($t) => in_array($t['state'] ?? '', ['ENDED', 'FILE_GENERATED'], true),
            );
            if (! $transcripts) {
                continue;
            }
            $names = $this->participantNames($record['name']);
            foreach ($transcripts as $transcript) {
                $docId ??= $transcript['docsDestination']['document'] ?? null;
                foreach ($this->pages(self::MEET . '/' . $transcript['name'] . '/entries', 'transcriptEntries') as $line) {
                    $entries[] = [
                        'speaker' => $names[$line['participant'] ?? ''] ?? null,
                        'time' => $line['startTime'] ?? null,
                        'text' => trim((string) ($line['text'] ?? '')),
                    ];
                    if (! empty($line['languageCode'])) {
                        $languages[] = strtolower(substr($line['languageCode'], 0, 2));
                    }
                }
            }
        }

        $entries = array_values(array_filter($entries, fn ($e) => $e['text'] !== ''));
        if (! $entries) {
            return null;
        }
        usort($entries, fn ($a, $b) => strcmp((string) $a['time'], (string) $b['time']));

        $text = implode("\n", array_map(function ($e) {
            $at = $e['time'] ? CarbonImmutable::parse($e['time'])->setTimezone(self::TIME_ZONE)->format('H:i') : null;

            return trim(($e['speaker'] ?? '?') . ($at ? " ({$at})" : '')) . ': ' . $e['text'];
        }, $entries));
        // The language most lines were spoken in
        $language = $languages ? (string) collect($languages)->countBy()->sortDesc()->keys()->first() : null;

        return new Transcript('meet_api', $text, $entries, $language, $docId);
    }

    /**
     * Route 2 (6.1): the Google Docs Gemini attached to the calendar event ("Notes by Gemini", with a Transcript
     * tab). Reads the transcript tab when there is one, else the whole document. Null when nothing is attached yet.
     */
    public function eventAttachmentText(string $eventId): ?Transcript
    {
        $event = $this->call('get', self::CALENDAR . '/calendars/primary/events/' . rawurlencode($eventId), ['supportsAttachments' => 'true']);
        $docs = array_values(array_filter($event['attachments'] ?? [], fn ($a) => ($a['mimeType'] ?? '') === 'application/vnd.google-apps.document' && ! empty($a['fileId'])));
        if (! $docs) {
            return null;
        }
        usort($docs, fn ($a, $b) => (int) ! preg_match('/transcri/i', $a['title'] ?? '') <=> (int) ! preg_match('/transcri/i', $b['title'] ?? ''));

        foreach ($docs as $doc) {
            $document = $this->call('get', self::DOCS . '/documents/' . rawurlencode($doc['fileId']), ['includeTabsContent' => 'true']);
            $tabs = $this->flattenTabs($document['tabs'] ?? []);
            $transcriptTab = collect($tabs)->first(fn ($t) => preg_match('/transcri/i', $t['title']));
            $text = trim($transcriptTab ? $transcriptTab['text'] : implode("\n\n", array_column($tabs, 'text')));
            if ($text === '' && isset($document['body'])) {
                $text = trim($this->bodyText($document['body']));
            }
            if ($text !== '') {
                return new Transcript('calendar_doc', $text, null, null, $doc['fileId']);
            }
        }

        return null;
    }

    /**
     * The timed events in the connected calendar between two moments, in start order: for importing meetings
     * HQ did not create (opportunities:import). Each: id, title, start, end (Africa/Lagos), Meet code and link,
     * attendees (email, name, response) and how many Google Docs are attached (Gemini's notes). Read-only.
     *
     * @return list<array{id: string, title: string, start: CarbonImmutable, end: CarbonImmutable, meet_code: ?string, meet_link: ?string, attendees: list<array{email: string, name: ?string, response: ?string}>, docs: int}>
     */
    public function listEvents(CarbonInterface $from, CarbonInterface $to): array
    {
        $events = [];
        $pageToken = null;
        do {
            $page = $this->call('get', self::CALENDAR . '/calendars/primary/events', array_filter([
                'timeMin' => $from->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'timeMax' => $to->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 250,
                'pageToken' => $pageToken,
            ]));
            foreach ($page['items'] ?? [] as $event) {
                // All-day entries (holidays, reminders) are not meetings
                if (empty($event['start']['dateTime']) || ($event['status'] ?? '') === 'cancelled') {
                    continue;
                }
                $events[] = [
                    'id' => (string) $event['id'],
                    'title' => trim((string) ($event['summary'] ?? '')),
                    'start' => CarbonImmutable::parse($event['start']['dateTime'])->setTimezone(self::TIME_ZONE),
                    'end' => CarbonImmutable::parse($event['end']['dateTime'] ?? $event['start']['dateTime'])->setTimezone(self::TIME_ZONE),
                    'meet_code' => $event['conferenceData']['conferenceId'] ?? null,
                    'meet_link' => $event['hangoutLink'] ?? null,
                    'attendees' => array_values(array_map(fn ($a) => [
                        'email' => strtolower((string) ($a['email'] ?? '')),
                        'name' => $a['displayName'] ?? null,
                        'response' => $a['responseStatus'] ?? null,
                    ], array_filter($event['attendees'] ?? [], fn ($a) => ! empty($a['email']) && empty($a['resource'])))),
                    'docs' => count(array_filter($event['attachments'] ?? [], fn ($a) => ($a['mimeType'] ?? '') === 'application/vnd.google-apps.document')),
                ];
            }
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken);

        return $events;
    }

    // ---- Plumbing ---------------------------------------------------------------------------------------------

    /** One API call with a fresh access token; a 401 refreshes the token once and retries */
    protected function call(string $method, string $url, array $query = [], ?array $body = null): array
    {
        for ($attempt = 0; ; $attempt++) {
            $token = $this->accessToken($attempt > 0);
            try {
                $request = Http::withToken($token)->acceptJson()->timeout(20)->connectTimeout(5);
                $target = $query ? $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $url;
                $response = match ($method) {
                    'get' => $request->get($target),
                    'delete' => $request->delete($target),
                    default => $request->{$method}($target, $body ?? []),
                };
            } catch (ConnectionException $e) {
                throw new GoogleUnavailable('failed', $e);
            }

            if ($response->status() === 401 && $attempt === 0) {
                continue;
            }
            if ($response->successful()) {
                return $response->json() ?? [];
            }
            throw $this->failure($response, $url);
        }
    }

    /** Follows nextPageToken; yields the items under $key */
    protected function pages(string $url, string $key): \Generator
    {
        $token = null;
        for ($page = 0; $page < 100; $page++) {
            $data = $this->call('get', $url, array_filter(['pageSize' => 100, 'pageToken' => $token]));
            yield from $data[$key] ?? [];
            $token = $data['nextPageToken'] ?? null;
            if (! $token) {
                return;
            }
        }
    }

    /** participants/{id} => display name */
    private function participantNames(string $record): array
    {
        $names = [];
        foreach ($this->pages(self::MEET . '/' . $record . '/participants', 'participants') as $p) {
            $names[$p['name']] = $p['signedinUser']['displayName'] ?? $p['anonymousUser']['displayName'] ?? $p['phoneUser']['displayName'] ?? null;
        }

        return $names;
    }

    private function failure(Response $response, string $url): GoogleUnavailable
    {
        $reason = (string) ($response->json('error.errors.0.reason') ?? $response->json('error.status') ?? '');
        $kind = match (true) {
            $response->status() === 429, in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded', 'dailyLimitExceeded', 'RESOURCE_EXHAUSTED'], true) => 'quota',
            $response->status() === 401 => 'revoked',
            $response->status() === 403 && in_array($reason, ['insufficientPermissions', 'PERMISSION_DENIED', 'ACCESS_TOKEN_SCOPE_INSUFFICIENT'], true) => 'scopes',
            in_array($response->status(), [404, 410], true) => 'not_found',
            default => 'failed',
        };
        if ($kind === 'revoked') {
            $this->markRevoked();
        }
        if (in_array($kind, ['failed', 'scopes'], true)) {
            Log::warning('Google: a call failed', ['api' => parse_url($url, PHP_URL_HOST), 'status' => $response->status(), 'reason' => $reason]);
        }

        return new GoogleUnavailable($kind);
    }

    /** A valid access token, from the cache or by refreshing (the cache holds it encrypted) */
    protected function accessToken(bool $fresh = false): string
    {
        if (! $this->hasClient() || Integrations::get('google.refresh_token') === null) {
            throw new GoogleUnavailable('not_connected');
        }
        if ($this->needsReconnect()) {
            throw new GoogleUnavailable('revoked');
        }
        if (! $fresh && ($cached = Cache::get(self::TOKEN_CACHE))) {
            try {
                return Crypt::decryptString($cached);
            } catch (Throwable) {
                Cache::forget(self::TOKEN_CACHE);
            }
        }

        $token = $this->tokenRequest(['refresh_token' => Integrations::get('google.refresh_token'), 'grant_type' => 'refresh_token']);

        return $this->cacheAccessToken($token);
    }

    private function cacheAccessToken(array $token): string
    {
        $access = (string) ($token['access_token'] ?? '');
        if ($access === '') {
            throw new GoogleUnavailable('failed');
        }
        $ttl = max(60, (int) ($token['expires_in'] ?? 3600) - 120);
        Cache::put(self::TOKEN_CACHE, Crypt::encryptString($access), $ttl);

        return $access;
    }

    /** POST to the token endpoint with the client's credentials */
    private function tokenRequest(array $params): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(20)->connectTimeout(5)->post(self::TOKEN_URL, $params + [
                'client_id' => Integrations::get('google.client_id'),
                'client_secret' => Integrations::get('google.client_secret'),
            ]);
        } catch (ConnectionException $e) {
            throw new GoogleUnavailable('failed', $e);
        }
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = (string) $response->json('error');
        if ($error === 'invalid_grant' && ($params['grant_type'] ?? '') === 'refresh_token') {
            $this->markRevoked();

            throw new GoogleUnavailable('revoked');
        }
        Log::warning('Google: the token request failed', ['status' => $response->status(), 'error' => $error]);

        throw new GoogleUnavailable(in_array($error, ['invalid_client', 'unauthorized_client'], true) ? 'client' : 'failed');
    }

    private function markRevoked(): void
    {
        if (Integrations::get('google.status') !== 'revoked') {
            Integrations::set('google.status', 'revoked');
            Log::warning('Google: access was withdrawn; reconnect in HQ › System › Integrations');
        }
        Cache::forget(self::TOKEN_CACHE);
    }

    /** The email in the ID token Google returned from its own token endpoint (over TLS, so not re-verified) */
    private function emailFromIdToken(?string $idToken): ?string
    {
        $parts = explode('.', (string) $idToken);
        if (count($parts) !== 3) {
            return null;
        }
        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) && ! empty($claims['email']) ? (string) $claims['email'] : null;
    }

    /** @return list<array{title: string, text: string}> every tab and child tab, in order */
    private function flattenTabs(array $tabs): array
    {
        $out = [];
        foreach ($tabs as $tab) {
            $out[] = [
                'title' => (string) ($tab['tabProperties']['title'] ?? ''),
                'text' => $this->bodyText($tab['documentTab']['body'] ?? []),
            ];
            array_push($out, ...$this->flattenTabs($tab['childTabs'] ?? []));
        }

        return $out;
    }

    private function bodyText(array $body): string
    {
        $text = '';
        foreach ($body['content'] ?? [] as $block) {
            foreach ($block['paragraph']['elements'] ?? [] as $element) {
                $text .= $element['textRun']['content'] ?? '';
            }
            foreach ($block['table']['tableRows'] ?? [] as $row) {
                foreach ($row['tableCells'] ?? [] as $cell) {
                    $text .= $this->bodyText($cell) . "\t";
                }
                $text .= "\n";
            }
        }

        return $text;
    }
}
