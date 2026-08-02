<?php

namespace App\Service;

use App\Entity\InboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GmailService
{
    public function __construct(
        private HttpClientInterface $http,
        private GmailTokenStore $store,
        private EntityManagerInterface $em,
    ) {}

    public function authorizationUrl(): string
    {
        $clientId = (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? '');
        $redirectUri = (string) ($_ENV['GOOGLE_REDIRECT_URI'] ?? 'http://localhost:8080/api/integrations/gmail/callback');
        if ($clientId === '') throw new \RuntimeException('GOOGLE_CLIENT_ID est absent du fichier .env.');
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/gmail.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $this->store->createState(),
        ]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    public function handleCallback(string $code, string $state): void
    {
        if (!$this->store->consumeState($state)) throw new \RuntimeException('État OAuth invalide ou expiré.');
        $response = $this->http->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? ''),
                'client_secret' => (string) ($_ENV['GOOGLE_CLIENT_SECRET'] ?? ''),
                'redirect_uri' => (string) ($_ENV['GOOGLE_REDIRECT_URI'] ?? ''),
                'grant_type' => 'authorization_code',
            ],
        ]);
        $token = $response->toArray();
        $token['created_at'] = time();
        $this->store->saveToken($token);
    }

    public function sync(): array
    {
        $token = $this->validToken();
        $query = (string) ($_ENV['GMAIL_SEARCH_QUERY'] ?? '(job OR mission OR candidature OR application) newer_than:30d');
        $list = $this->http->request('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/messages', [
            'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
            'query' => ['q' => $query, 'maxResults' => 50],
        ])->toArray(false);

        $imported = 0;
        foreach ($list['messages'] ?? [] as $item) {
            $gmailId = (string) ($item['id'] ?? '');
            if ($gmailId === '' || $this->em->getRepository(InboxMessage::class)->findOneBy(['gmailMessageId' => $gmailId])) continue;
            $data = $this->http->request('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$gmailId, [
                'headers' => ['Authorization' => 'Bearer '.$token['access_token']],
                'query' => ['format' => 'metadata'],
            ])->toArray(false);
            $headers = [];
            foreach ($data['payload']['headers'] ?? [] as $header) $headers[strtolower((string) $header['name'])] = (string) $header['value'];
            $receivedAt = isset($data['internalDate']) ? (new \DateTimeImmutable())->setTimestamp((int) ((int) $data['internalDate'] / 1000)) : new \DateTimeImmutable();
            $subject = $headers['subject'] ?? '';
            $snippet = (string) ($data['snippet'] ?? '');
            $message = (new InboxMessage($gmailId, (string) ($data['threadId'] ?? '')))->fill(
                $headers['from'] ?? '', $subject, $snippet, $receivedAt, $this->classify($subject.' '.$snippet)
            );
            $this->em->persist($message);
            ++$imported;
        }
        $this->em->flush();
        return ['imported' => $imported, 'found' => count($list['messages'] ?? [])];
    }

    private function validToken(): array
    {
        $token = $this->store->getToken();
        if ($token === null) throw new \RuntimeException('Gmail n’est pas connecté.');
        $expiresAt = (int) ($token['created_at'] ?? 0) + (int) ($token['expires_in'] ?? 3600) - 60;
        if (time() < $expiresAt) return $token;
        if (empty($token['refresh_token'])) throw new \RuntimeException('Le jeton Gmail a expiré. Reconnectez Gmail.');
        $refreshed = $this->http->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? ''),
                'client_secret' => (string) ($_ENV['GOOGLE_CLIENT_SECRET'] ?? ''),
                'refresh_token' => $token['refresh_token'],
                'grant_type' => 'refresh_token',
            ],
        ])->toArray();
        $token = array_merge($token, $refreshed, ['created_at' => time()]);
        $this->store->saveToken($token);
        return $token;
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
