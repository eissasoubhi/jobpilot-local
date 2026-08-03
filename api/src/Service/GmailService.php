<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GmailService
{
    private const DEFAULT_REDIRECT_URI = 'http://localhost:8080/api/integrations/gmail/callback';

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

    public function authorizationUrl(): string
    {
        $configuration = $this->requireConfiguration();
        $query = http_build_query([
            'client_id' => $this->env('GOOGLE_CLIENT_ID'),
            'redirect_uri' => $configuration['redirectUri'],
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/gmail.readonly',
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

        $token = $response->toArray();
        $token['created_at'] = time();
        $this->store->saveToken($token);
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

        $configuration = $this->requireConfiguration();
        $refreshed = $this->http->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->env('GOOGLE_CLIENT_ID'),
                'client_secret' => $this->env('GOOGLE_CLIENT_SECRET'),
                'refresh_token' => $token['refresh_token'],
                'grant_type' => 'refresh_token',
            ],
        ])->toArray();
        $token = array_merge($token, $refreshed, ['created_at' => time()]);
        $this->store->saveToken($token);

        return $token;
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
