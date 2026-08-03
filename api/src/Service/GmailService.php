<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GmailService
{
    private const DEFAULT_REDIRECT_URI = 'http://localhost:8080/api/integrations/gmail/callback';
    private const READ_SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';
    private const SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    public function __construct(
        private HttpClientInterface $http,
        private GmailTokenStore $store,
        private EntityManagerInterface $em,
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

    public function hasSendPermission(): bool
    {
        $token = $this->store->getToken();
        if ($token === null) {
            return false;
        }

        if ($this->tokenHasScope($token, self::SEND_SCOPE)) {
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

            return in_array(self::SEND_SCOPE, $scopes, true);
        } catch (\Throwable) {
            return false;
        }
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
        if ($code === '') {
            throw new \RuntimeException('Google n’a retourné aucun code d’autorisation.');
        }

        if ($state === '' || !$this->store->consumeState($state)) {
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
        if (!$this->tokenHasScope($token, self::SEND_SCOPE)) {
            $scopes = $this->resolveGrantedScopes($token);
            if ($scopes !== []) {
                $token['scope'] = implode(' ', $scopes);
                $token['granted_scopes'] = $scopes;
                $this->store->saveToken($token);
            }

            if (!in_array(self::SEND_SCOPE, $scopes, true)) {
                throw new \RuntimeException('Gmail doit être reconnecté avec l’autorisation d’envoi.');
            }
        }

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
     * @return array{imported: int, found: int}
     */
    public function sync(): array
    {
        $token = $this->validToken();
        $query = $this->env('GMAIL_SEARCH_QUERY', '(job OR mission OR candidature OR application) newer_than:30d');
        $list = $this->http->request('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/messages', [
            'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
            'query' => ['q' => $query, 'maxResults' => 50],
        ])->toArray(false);

        $imported = 0;
        foreach ($list['messages'] ?? [] as $item) {
            $gmailId = (string) ($item['id'] ?? '');
            if ($gmailId === '' || $this->em->getRepository(InboxMessage::class)->findOneBy(['gmailMessageId' => $gmailId])) {
                continue;
            }

            $data = $this->http->request('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$gmailId, [
                'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
                'query' => ['format' => 'metadata'],
            ])->toArray(false);
            $headers = [];
            foreach ($data['payload']['headers'] ?? [] as $header) {
                $headers[strtolower((string) $header['name'])] = (string) $header['value'];
            }

            $receivedAt = isset($data['internalDate'])
                ? (new \DateTimeImmutable())->setTimestamp((int) ((int) $data['internalDate'] / 1000))
                : new \DateTimeImmutable();
            $subject = $headers['subject'] ?? '';
            $snippet = (string) ($data['snippet'] ?? '');
            $message = (new InboxMessage($gmailId, (string) ($data['threadId'] ?? '')))->fill(
                $headers['from'] ?? '',
                $subject,
                $snippet,
                $receivedAt,
                $this->classify($subject.' '.$snippet),
            );
            $this->em->persist($message);
            ++$imported;
        }

        $this->em->flush();

        return ['imported' => $imported, 'found' => count($list['messages'] ?? [])];
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $token
     */
    private function tokenHasScope(array $token, string $scope): bool
    {
        return in_array($scope, $this->tokenScopes($token), true);
    }

    /**
     * @param array<string, mixed> $data
     */
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

    /**
     * @param array<string, mixed> $data
     */
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
            throw new \RuntimeException(
                'Configuration Gmail incomplète : '.implode(', ', $configuration['missingVariables']).'.',
            );
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

    private function classify(string $text): string
    {
        $value = mb_strtolower($text);

        return match (true) {
            str_contains($value, 'entretien'), str_contains($value, 'interview') => 'INTERVIEW_REQUEST',
            str_contains($value, 'malheureusement'), str_contains($value, 'not retained'), str_contains($value, 'rejet') => 'REJECTION',
            str_contains($value, 'candidature reçue'), str_contains($value, 'application received') => 'APPLICATION_CONFIRMATION',
            str_contains($value, 'alerte'), str_contains($value, 'new jobs'), str_contains($value, 'offres pour vous') => 'JOB_ALERT',
            str_contains($value, 'tjm'), str_contains($value, 'disponibilité'), str_contains($value, 'recruiter') => 'RECRUITER_REPLY',
            default => 'UNKNOWN',
        };
    }
}
