<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

use App\Entity\ConnectorDeadLetter;
use Doctrine\ORM\EntityManagerInterface;

final class ConnectorDeadLetterService
{
    /** @var array<string, ConnectorDeadLetter> */
    private array $pending = [];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @param array<string, mixed> $payload */
    public function recordPayloadFailure(string $connectorCode, array $payload, \Throwable $exception): ConnectorDeadLetter
    {
        $identity = $this->payloadIdentity($payload);

        return $this->record(
            $connectorCode,
            ConnectorDeadLetter::STAGE_IMPORT,
            $identity['fingerprint'],
            $exception,
            $identity['externalId'],
            $identity['sourceUrl'],
            $identity['title'],
        );
    }

    /** @param array<string, mixed> $payload */
    public function recordMissingExternalId(string $connectorCode, array $payload): ConnectorDeadLetter
    {
        $identity = $this->payloadIdentity($payload);

        return $this->record(
            $connectorCode,
            ConnectorDeadLetter::STAGE_IMPORT,
            $identity['fingerprint'],
            new \InvalidArgumentException('Offre ignorée car son identifiant externe est vide.'),
            null,
            $identity['sourceUrl'],
            $identity['title'],
        );
    }

    public function recordConnectorFailure(string $connectorCode, \Throwable $exception): ConnectorDeadLetter
    {
        return $this->record(
            $connectorCode,
            ConnectorDeadLetter::STAGE_SEARCH,
            hash('sha256', strtolower(trim($connectorCode)).'|search'),
            $exception,
        );
    }

    /** @param array<string, mixed> $payload */
    public function resolvePayload(string $connectorCode, array $payload): void
    {
        $identity = $this->payloadIdentity($payload);
        $this->resolveFingerprint($connectorCode, ConnectorDeadLetter::STAGE_IMPORT, $identity['fingerprint']);
    }

    public function resolveConnectorSearch(string $connectorCode): void
    {
        $this->resolveFingerprint(
            $connectorCode,
            ConnectorDeadLetter::STAGE_SEARCH,
            hash('sha256', strtolower(trim($connectorCode)).'|search'),
        );
    }

    /** @return list<array<string, mixed>> */
    public function list(string $state = ConnectorDeadLetter::STATE_OPEN, int $limit = 50): array
    {
        $state = strtoupper(trim($state));
        if (!in_array($state, [
            ConnectorDeadLetter::STATE_TRACKING,
            ConnectorDeadLetter::STATE_OPEN,
            ConnectorDeadLetter::STATE_RESOLVED,
            'ALL',
        ], true)) {
            throw new \InvalidArgumentException('État de dead-letter invalide.');
        }

        $limit = max(1, min(200, $limit));
        $criteria = $state === 'ALL' ? [] : ['state' => $state];
        $entries = $this->em->getRepository(ConnectorDeadLetter::class)->findBy(
            $criteria,
            ['lastFailedAt' => 'DESC'],
            $limit,
        );

        return array_map(
            static fn (ConnectorDeadLetter $entry): array => $entry->toArray(),
            $entries,
        );
    }

    public function openCount(string $connectorCode): int
    {
        return $this->em->getRepository(ConnectorDeadLetter::class)->count([
            'connectorCode' => strtolower(trim($connectorCode)),
            'state' => ConnectorDeadLetter::STATE_OPEN,
        ]);
    }

    /** @return array<string, mixed> */
    public function resolveById(int $id): array
    {
        $entry = $this->em->getRepository(ConnectorDeadLetter::class)->find($id);
        if (!$entry instanceof ConnectorDeadLetter) {
            throw new \InvalidArgumentException('Dead-letter introuvable.');
        }

        $entry->resolve();
        $this->em->flush();

        return $entry->toArray();
    }

    public function pruneStaleTracking(int $days = 7): int
    {
        $days = max(1, min(90, $days));
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $days));
        $entries = $this->em->getRepository(ConnectorDeadLetter::class)->createQueryBuilder('dead')
            ->andWhere('dead.state = :state')
            ->andWhere('dead.lastFailedAt < :threshold')
            ->setParameter('state', ConnectorDeadLetter::STATE_TRACKING)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        $removed = 0;
        foreach ($entries as $entry) {
            if (!$entry instanceof ConnectorDeadLetter) {
                continue;
            }
            $this->em->remove($entry);
            ++$removed;
        }

        return $removed;
    }

    private function record(
        string $connectorCode,
        string $stage,
        string $fingerprint,
        \Throwable $exception,
        ?string $externalId = null,
        ?string $sourceUrl = null,
        ?string $title = null,
    ): ConnectorDeadLetter {
        $connectorCode = strtolower(trim($connectorCode));
        $key = $this->key($connectorCode, $stage, $fingerprint);
        $entry = $this->pending[$key] ?? $this->em->getRepository(ConnectorDeadLetter::class)->findOneBy([
            'connectorCode' => $connectorCode,
            'stage' => $stage,
            'fingerprint' => $fingerprint,
        ]);

        $errorClass = get_debug_type($exception);
        $errorMessage = $exception->getMessage();
        if ($entry instanceof ConnectorDeadLetter) {
            $entry->recordFailure($errorClass, $errorMessage, $externalId, $sourceUrl, $title);
        } else {
            $entry = new ConnectorDeadLetter(
                $connectorCode,
                $stage,
                $fingerprint,
                $errorClass,
                $errorMessage,
                $externalId,
                $sourceUrl,
                $title,
            );
            $this->em->persist($entry);
        }

        $this->pending[$key] = $entry;

        return $entry;
    }

    private function resolveFingerprint(string $connectorCode, string $stage, string $fingerprint): void
    {
        $connectorCode = strtolower(trim($connectorCode));
        $key = $this->key($connectorCode, $stage, $fingerprint);
        $entry = $this->pending[$key] ?? $this->em->getRepository(ConnectorDeadLetter::class)->findOneBy([
            'connectorCode' => $connectorCode,
            'stage' => $stage,
            'fingerprint' => $fingerprint,
        ]);

        if ($entry instanceof ConnectorDeadLetter) {
            $entry->resolve();
            $this->pending[$key] = $entry;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{fingerprint: string, externalId: ?string, sourceUrl: ?string, title: ?string}
     */
    private function payloadIdentity(array $payload): array
    {
        $externalId = $this->optional((string) ($payload['externalId'] ?? ''), 180);
        $sourceUrl = $this->sanitizedSourceUrl((string) ($payload['sourceUrl'] ?? ''));
        $title = $this->optional((string) ($payload['title'] ?? ''), 255);

        $identity = match (true) {
            $externalId !== null => 'external:'.$externalId,
            $sourceUrl !== null => 'url:'.$sourceUrl,
            $title !== null => 'title:'.$title,
            default => 'anonymous:missing-identity',
        };

        return [
            'fingerprint' => hash('sha256', $identity),
            'externalId' => $externalId,
            'sourceUrl' => $sourceUrl,
            'title' => $title,
        ];
    }

    private function sanitizedSourceUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';

        return mb_substr($scheme.'://'.$host.$port.($path !== '' ? $path : '/'), 0, 500);
    }

    private function optional(string $value, int $maxLength): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    private function key(string $connectorCode, string $stage, string $fingerprint): string
    {
        return $connectorCode.'|'.$stage.'|'.$fingerprint;
    }
}
