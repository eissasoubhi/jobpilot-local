<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Messaging\Application\GmailJobAlertExtractor;
use App\Messaging\Application\GmailMessageClassifier;
use App\Messaging\Infrastructure\Gmail\GmailMessageDecoder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GmailService
{
    private const DEFAULT_REDIRECT_URI = 'http://localhost:8080/api/integrations/gmail/callback';
    private const READ_SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';
    private const SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    /** @var array{found: int, imported: int, duplicates: int, failed: int, offersFound: int, associated: int, actionRequired: int} */
    private array $lastSyncSummary = [
        'found' => 0,
        'imported' => 0,
        'duplicates' => 0,
        'failed' => 0,
        'offersFound' => 0,
        'associated' => 0,
        'actionRequired' => 0,
    ];

    public function __construct(
        private HttpClientInterface $http,
        private GmailTokenStore $store,
        private EntityManagerInterface $em,
        private GmailMessageDecoder $decoder,
        private GmailMessageClassifier $classifier,
        private GmailJobAlertExtractor $jobExtractor,
    ) {}

    /**
     * @return array{configured: bool, missingVariables: list<string>, redirectUri: string, startUrl: string}
     */
    public function configuration(): array
    {
        $missing = [];
        if ($this->env('GOOGLE_CLIENT_ID') === '') {
            $missing[] = 'GOOGLE_CLIENT_ID';
        }
        if ($this->env('GOOGLE_CLIENT_SECRET') === '') {
            $missing[] = 'GOOGLE_CLIENT_SECRET';
        }

        return [
            'configured' => $missing === [],
            'missingVariables' => $missing,
            'redirectUri' => $this->redirectUri(),
            'startUrl' => $this->startUrl(),
        ];
    }

    public function hasReadPermission(): bool
    {
        return $this->hasPermission(self::READ_SCOPE);
    }

    public function hasSendPermission(): bool
    {
        return $this->hasPermission(self::SEND_SCOPE);
    }

    public function authorizationUrl(): string
    {
        $configuration = $this->requireConfiguration();
        $query = http_build_query([
            'client_id' => $this->env('GOOGLE_CLIENT_ID'),
            'redirect_uri' => $configuration['redirectUri'],
            'response_type' => 'code',
            'scope' => self::READ_SCOPE.' '.self::SEND_SCOPE,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $this->store->createState(),
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    public function handleCallback(string $code, string $state): void
    {
        if (trim($code) === '') {
            throw new \RuntimeException('Google n’a retourné aucun code d’autorisation.');
        }
        if (trim($state) === '' || !$this->store->consumeState($state)) {
            throw new \RuntimeException('État OAuth invalide ou expiré. Relancez la connexion Gmail.');
        }

        $configuration = $this->requireConfiguration();
        $response = $this->http->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $this->env('GOOGLE_CLIENT_ID'),
                'client_secret' => $this->env('GOOGLE_CLIENT_SECRET'),
                'redirect_uri' => $configuration['redirectUri'],
                'grant_type' => 'authorization_code',
            ],
        ]);
        $statusCode = $response->getStatusCode();
        $token = $response->toArray(false);
        if ($statusCode >= 400) {
            throw new \RuntimeException($this->oauthErrorMessage($token, 'Connexion Gmail refusée par Google.'));
        }

        $token['created_at'] = time();
        $scopes = $this->resolveGrantedScopes($token);
        if ($scopes !== []) {
            $token['scope'] = implode(' ', $scopes);
            $token['granted_scopes'] = $scopes;
        }
        $this->store->saveToken($token);
    }

    /**
     * @param list<array{path: string, filename: string, mimeType: string}> $attachments
     * @return array{id: string, threadId: string|null}
     */
    public function sendEmail(string $to, string $subject, string $body, array $attachments = []): array
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Adresse e-mail de candidature invalide.');
        }

        $token = $this->validToken();
        $this->requireScope($token, self::SEND_SCOPE, 'Gmail doit être reconnecté avec l’autorisation d’envoi.');
        $mime = $this->mimeMessage($to, $subject, $body, $attachments);
        $raw = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');

        try {
            $response = $this->http->request('POST', 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
                'json' => ['raw' => $raw],
            ]);
            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $error) {
            throw new \RuntimeException('Impossible de joindre Gmail : '.$error->getMessage(), 0, $error);
        }

        if ($statusCode >= 400) {
            throw new \RuntimeException($this->gmailErrorMessage($statusCode, $data));
        }

        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            throw new \RuntimeException('Gmail n’a retourné aucun identifiant de message.');
        }

        return ['id' => $id, 'threadId' => isset($data['threadId']) ? (string) $data['threadId'] : null];
    }

    /**
     * Synchronisation directe utilisée par les tests et les appels internes hors registre.
     *
     * @return array{found: int, imported: int, duplicates: int, failed: int, offersFound: int, associated: int, actionRequired: int}
     */
    public function sync(): array
    {
        $this->collect(true);

        return $this->lastSyncSummary;
    }

    /**
     * Point d’entrée du connecteur JobDiscovery. Les entités InboxMessage restent dans
     * la même unité de travail Doctrine que les offres, puis sont validées par le service
     * de synchronisation des connecteurs.
     *
     * @return list<array<string, mixed>>
     */
    public function collectJobOffers(): array
    {
        return $this->collect(false);
    }

    /**
     * @return array{found: int, imported: int, duplicates: int, failed: int, offersFound: int, associated: int, actionRequired: int}
     */
    public function lastSyncSummary(): array
    {
        return $this->lastSyncSummary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collect(bool $flush): array
    {
        $token = $this->validToken();
        $this->requireScope($token, self::READ_SCOPE, 'Gmail doit être reconnecté avec l’autorisation de lecture.');

        $summary = [
            'found' => 0,
            'imported' => 0,
            'duplicates' => 0,
            'failed' => 0,
            'offersFound' => 0,
            'associated' => 0,
            'actionRequired' => 0,
        ];
        $offers = [];
        $offerIds = [];
        $messageIds = $this->listMessageIds($token);
        $summary['found'] = count($messageIds);
        $inboxRepository = $this->em->getRepository(InboxMessage::class);

        foreach ($messageIds as $gmailId) {
            if ($inboxRepository->findOneBy(['gmailMessageId' => $gmailId]) instanceof InboxMessage) {
                ++$summary['duplicates'];
                continue;
            }

            try {
                $data = $this->fetchMessage($token, $gmailId);
                $decoded = $this->decoder->decode($data);
                $classification = $this->classifier->classify(
                    $decoded['subject'],
                    $decoded['sender'],
                    $decoded['plainBody'] !== '' ? $decoded['plainBody'] : $decoded['snippet'],
                );
                $sourcePlatform = $this->detectSourcePlatform(
                    $decoded['sender'].' '.$decoded['subject'].' '.$decoded['plainBody'].' '.$decoded['htmlBody'],
                );

                $message = (new InboxMessage($decoded['gmailMessageId'], $decoded['threadId']))->fill(
                    $decoded['sender'],
                    $decoded['subject'],
                    $decoded['snippet'],
                    $decoded['receivedAt'],
                    $classification['category'],
                    $decoded['recipient'],
                    $decoded['replyTo'],
                    $decoded['plainBody'],
                    $classification['actionRequired'],
                    $classification['reason'],
                    $sourcePlatform,
                );

                $application = $this->matchApplication(
                    $classification['category'],
                    $decoded['subject'].' '.$decoded['plainBody'].' '.$decoded['snippet'],
                );
                if ($application !== null) {
                    $application->applyInboxCategory($classification['category']);
                    $message->associate($application);
                    ++$summary['associated'];
                }
                if ($classification['actionRequired']) {
                    ++$summary['actionRequired'];
                }

                $this->em->persist($message);
                ++$summary['imported'];

                foreach ($this->jobExtractor->extract(
                    $decoded['gmailMessageId'],
                    $classification['category'],
                    $decoded['subject'],
                    $decoded['sender'],
                    $decoded['plainBody'],
                    $decoded['htmlBody'],
                    $decoded['receivedAt'],
                ) as $offer) {
                    $externalId = (string) ($offer['externalId'] ?? '');
                    if ($externalId === '' || isset($offerIds[$externalId])) {
                        continue;
                    }
                    $offerIds[$externalId] = true;
                    $offers[] = $offer;
                }
            } catch (\Throwable) {
                ++$summary['failed'];
            }
        }

        $summary['offersFound'] = count($offers);
        $this->lastSyncSummary = $summary;
        if ($flush) {
            $this->em->flush();
        }

        return $offers;
    }

    /**
     * @param array<string, mixed> $token
     * @return list<string>
     */
    private function listMessageIds(array $token): array
    {
        $query = $this->env(
            'GMAIL_SEARCH_QUERY',
            '(job OR mission OR candidature OR application OR recruiter OR entretien) newer_than:30d',
        );
        $maxResults = max(10, min(500, (int) $this->env('GMAIL_MAX_RESULTS', '100')));
        $maxPages = max(1, min(10, (int) $this->env('GMAIL_MAX_PAGES', '3')));
        $ids = [];
        $pageToken = null;

        for ($page = 0; $page < $maxPages && count($ids) < $maxResults; ++$page) {
            $queryParameters = [
                'q' => $query,
                'maxResults' => min(100, $maxResults - count($ids)),
            ];
            if ($pageToken !== null) {
                $queryParameters['pageToken'] = $pageToken;
            }

            $response = $this->http->request('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/messages', [
                'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
                'query' => $queryParameters,
            ]);
            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
            if ($statusCode >= 400) {
                throw new \RuntimeException($this->gmailReadErrorMessage($statusCode, $data));
            }

            foreach ($data['messages'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = trim((string) ($item['id'] ?? ''));
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }

            $pageToken = isset($data['nextPageToken']) && trim((string) $data['nextPageToken']) !== ''
                ? trim((string) $data['nextPageToken'])
                : null;
            if ($pageToken === null) {
                break;
            }
        }

        return array_slice(array_keys($ids), 0, $maxResults);
    }

    /**
     * @param array<string, mixed> $token
     * @return array<string, mixed>
     */
    private function fetchMessage(array $token, string $gmailId): array
    {
        $response = $this->http->request(
            'GET',
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/'.rawurlencode($gmailId),
            [
                'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
                'query' => ['format' => 'full'],
            ],
        );
        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);
        if ($statusCode >= 400) {
            throw new \RuntimeException($this->gmailReadErrorMessage($statusCode, $data));
        }

        return $data;
    }

    private function matchApplication(string $category, string $text): ?Application
    {
        if (!in_array($category, [
            'APPLICATION_CONFIRMATION', 'APPLICATION_REPLY', 'INTERVIEW_REQUEST',
            'REJECTION', 'INFORMATION_REQUEST',
        ], true)) {
            return null;
        }

        $normalizedText = $this->normalize($text);
        if ($normalizedText === '') {
            return null;
        }

        $applications = $this->em->getRepository(Application::class)->findBy([], ['createdAt' => 'DESC'], 150);
        $best = null;
        $bestScore = 0;
        foreach ($applications as $application) {
            if (!$application instanceof Application) {
                continue;
            }
            $job = $application->getJobOffer();
            $title = $this->normalize($job->getTitle());
            $company = $this->normalize($job->getCompany());
            $score = 0;
            if (mb_strlen($title) >= 6 && str_contains($normalizedText, $title)) {
                $score += 6;
            } else {
                foreach ($this->significantWords($title) as $word) {
                    if (str_contains($normalizedText, $word)) {
                        ++$score;
                    }
                }
            }
            if (mb_strlen($company) >= 3 && str_contains($normalizedText, $company)) {
                $score += 4;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $application;
            }
        }

        return $bestScore >= 4 && $best instanceof Application ? $best : null;
    }

    /** @return list<string> */
    private function significantWords(string $value): array
    {
        $words = preg_split('/[^a-z0-9+#.]+/u', $value) ?: [];
        $ignored = ['senior', 'junior', 'developpeur', 'developer', 'engineer', 'full', 'stack', 'backend', 'frontend'];

        return array_values(array_filter(
            array_unique($words),
            static fn (string $word): bool => mb_strlen($word) >= 4 && !in_array($word, $ignored, true),
        ));
    }

    private function detectSourcePlatform(string $text): ?string
    {
        $value = $this->normalize($text);

        return match (true) {
            str_contains($value, 'linkedin') => 'LinkedIn',
            str_contains($value, 'indeed') => 'Indeed',
            str_contains($value, 'apec') => 'APEC',
            str_contains($value, 'hellowork') => 'Hellowork',
            str_contains($value, 'welcome to the jungle'), str_contains($value, 'welcometothejungle') => 'Welcome to the Jungle',
            str_contains($value, 'free-work'), str_contains($value, 'free work') => 'Free-Work',
            str_contains($value, 'lesjeudis') => 'LesJeudis',
            str_contains($value, 'le hibou'), str_contains($value, 'lehibou') => 'Le Hibou',
            str_contains($value, 'france travail') => 'France Travail',
            default => null,
        };
    }

    /**
     * @param list<array{path: string, filename: string, mimeType: string}> $attachments
     */
    private function mimeMessage(string $to, string $subject, string $body, array $attachments): string
    {
        $boundary = 'jobpilot_'.bin2hex(random_bytes(16));
        $lines = [
            'To: '.$to,
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="'.$boundary.'"',
            '',
            '--'.$boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($body), 76, "\r\n")),
        ];

        foreach ($attachments as $attachment) {
            $path = $attachment['path'];
            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Le CV sélectionné est introuvable ou illisible.');
            }
            $content = file_get_contents($path);
            if ($content === false) {
                throw new \RuntimeException('Impossible de lire le CV sélectionné.');
            }

            $filename = str_replace(["\r", "\n"], '', $attachment['filename']);
            $mimeType = trim($attachment['mimeType']) !== '' ? $attachment['mimeType'] : 'application/octet-stream';
            $lines[] = '--'.$boundary;
            $lines[] = 'Content-Type: '.$mimeType;
            $lines[] = "Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($filename);
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = rtrim(chunk_split(base64_encode($content), 76, "\r\n"));
        }

        $lines[] = '--'.$boundary.'--';
        $lines[] = '';

        return implode("\r\n", $lines);
    }

    /** @return array<string, mixed> */
    private function validToken(): array
    {
        $token = $this->store->getToken();
        if ($token === null) {
            throw new \RuntimeException('Gmail n’est pas connecté.');
        }

        $expiresAt = (int) ($token['created_at'] ?? 0) + (int) ($token['expires_in'] ?? 3600) - 60;
        if (time() < $expiresAt) {
            return $token;
        }
        if (empty($token['refresh_token'])) {
            throw new \RuntimeException('Le jeton Gmail a expiré. Reconnectez Gmail.');
        }

        $this->requireConfiguration();
        $response = $this->http->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->env('GOOGLE_CLIENT_ID'),
                'client_secret' => $this->env('GOOGLE_CLIENT_SECRET'),
                'refresh_token' => $token['refresh_token'],
                'grant_type' => 'refresh_token',
            ],
        ]);
        $statusCode = $response->getStatusCode();
        $refreshed = $response->toArray(false);
        if ($statusCode >= 400) {
            throw new \RuntimeException($this->oauthErrorMessage($refreshed, 'Le renouvellement Gmail a échoué.'));
        }

        $token = array_merge($token, $refreshed, ['created_at' => time()]);
        $scopes = $this->tokenScopes($token);
        if ($scopes !== []) {
            $token['granted_scopes'] = $scopes;
        }
        $this->store->saveToken($token);

        return $token;
    }

    private function hasPermission(string $scope): bool
    {
        $token = $this->store->getToken();
        if ($token === null) {
            return false;
        }
        if ($this->tokenHasScope($token, $scope)) {
            return true;
        }
        if ($this->tokenScopes($token) !== []) {
            return false;
        }

        try {
            $scopes = $this->resolveGrantedScopes($token);
            if ($scopes !== []) {
                $token['scope'] = implode(' ', $scopes);
                $token['granted_scopes'] = $scopes;
                $this->store->saveToken($token);
            }

            return in_array($scope, $scopes, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $token */
    private function requireScope(array &$token, string $scope, string $message): void
    {
        if ($this->tokenHasScope($token, $scope)) {
            return;
        }
        $scopes = $this->resolveGrantedScopes($token);
        if ($scopes !== []) {
            $token['scope'] = implode(' ', $scopes);
            $token['granted_scopes'] = $scopes;
            $this->store->saveToken($token);
        }
        if (!in_array($scope, $scopes, true)) {
            throw new \RuntimeException($message);
        }
    }

    /**
     * @param array<string, mixed> $token
     * @return list<string>
     */
    private function resolveGrantedScopes(array $token): array
    {
        $scopes = $this->tokenScopes($token);
        if ($scopes !== []) {
            return $scopes;
        }
        $accessToken = trim((string) ($token['access_token'] ?? ''));
        if ($accessToken === '') {
            return [];
        }

        $response = $this->http->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
            'query' => ['access_token' => $accessToken],
        ]);
        if ($response->getStatusCode() >= 400) {
            return [];
        }

        return $this->tokenScopes($response->toArray(false));
    }

    /**
     * @param array<string, mixed> $token
     * @return list<string>
     */
    private function tokenScopes(array $token): array
    {
        $rawScopes = $token['granted_scopes'] ?? $token['scope'] ?? [];
        if (is_string($rawScopes)) {
            $rawScopes = preg_split('/\s+/', trim($rawScopes)) ?: [];
        }
        if (!is_array($rawScopes)) {
            return [];
        }

        $scopes = [];
        foreach ($rawScopes as $scope) {
            if (is_string($scope) && trim($scope) !== '') {
                $scopes[] = trim($scope);
            }
        }

        return array_values(array_unique($scopes));
    }

    /** @param array<string, mixed> $token */
    private function tokenHasScope(array $token, string $scope): bool
    {
        return in_array($scope, $this->tokenScopes($token), true);
    }

    /** @param array<string, mixed> $data */
    private function gmailErrorMessage(int $statusCode, array $data): string
    {
        $message = trim((string) ($data['error']['message'] ?? $data['error_description'] ?? ''));
        $reason = trim((string) ($data['error']['errors'][0]['reason'] ?? ''));
        if ($statusCode === 401) {
            return 'Gmail a refusé le jeton. Déconnecte puis reconnecte Gmail.';
        }
        if ($statusCode === 403 && (
            str_contains(mb_strtolower($message), 'scope')
            || str_contains(mb_strtolower($reason), 'permission')
        )) {
            return 'Gmail refuse l’envoi car l’autorisation gmail.send manque. Déconnecte puis reconnecte Gmail en acceptant le droit d’envoi.';
        }

        return 'Envoi Gmail impossible'.($message !== '' ? ' : '.$message : ' (HTTP '.$statusCode.').');
    }

    /** @param array<string, mixed> $data */
    private function gmailReadErrorMessage(int $statusCode, array $data): string
    {
        $message = trim((string) ($data['error']['message'] ?? $data['error_description'] ?? ''));
        if ($statusCode === 401) {
            return 'Gmail a refusé le jeton de lecture. Déconnecte puis reconnecte Gmail.';
        }
        if ($statusCode === 403) {
            return 'Gmail refuse la lecture. Reconnecte Gmail en acceptant gmail.readonly.';
        }

        return 'Lecture Gmail impossible'.($message !== '' ? ' : '.$message : ' (HTTP '.$statusCode.').');
    }

    /** @param array<string, mixed> $data */
    private function oauthErrorMessage(array $data, string $fallback): string
    {
        $message = trim((string) ($data['error_description'] ?? $data['error']['message'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }

    /**
     * @return array{configured: true, missingVariables: list<string>, redirectUri: string, startUrl: string}
     */
    private function requireConfiguration(): array
    {
        $configuration = $this->configuration();
        if (!$configuration['configured']) {
            throw new \RuntimeException('Configuration Gmail incomplète : '.implode(', ', $configuration['missingVariables']).'.');
        }

        /** @var array{configured: true, missingVariables: list<string>, redirectUri: string, startUrl: string} $configuration */
        return $configuration;
    }

    private function redirectUri(): string
    {
        return $this->env('GOOGLE_REDIRECT_URI', self::DEFAULT_REDIRECT_URI);
    }

    private function startUrl(): string
    {
        $redirectUri = $this->redirectUri();
        if (str_ends_with($redirectUri, '/callback')) {
            return substr($redirectUri, 0, -strlen('/callback')).'/start';
        }

        return 'http://localhost:8080/api/integrations/gmail/start';
    }

    private function env(string $name, string $default = ''): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', '’' => "'",
        ]);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
