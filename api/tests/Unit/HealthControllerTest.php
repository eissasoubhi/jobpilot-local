<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\HealthController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends TestCase
{
    public function testLivenessDoesNotTouchDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('executeQuery');

        $response = (new HealthController($connection))->live();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['status' => 'ok', 'check' => 'liveness'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testReadinessChecksDatabaseConnectivity(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(self::once())->method('fetchOne')->willReturn(1);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeQuery')->with('SELECT 1')->willReturn($result);

        $response = (new HealthController($connection))->ready();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['status' => 'ok', 'check' => 'readiness'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testReadinessFailsClosedWithoutLeakingDatabaseDetails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeQuery')->with('SELECT 1')->willThrowException(new \RuntimeException('secret database detail'));

        $response = (new HealthController($connection))->ready();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame(
            ['status' => 'unavailable', 'check' => 'readiness'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('secret database detail', (string) $response->getContent());
    }
}
