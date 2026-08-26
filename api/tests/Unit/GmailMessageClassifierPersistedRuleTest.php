<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\InboxSenderClassificationRule;
use App\Messaging\Application\GmailMessageClassifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class GmailMessageClassifierPersistedRuleTest extends TestCase
{
    public function testPersistedAlertRuleWinsOverRecruiterKeywords(): void
    {
        $rule = new InboxSenderClassificationRule('alerts@example.com', 'JOB_ALERT');
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['senderKey' => 'alerts@example.com'])
            ->willReturn($rule);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(InboxSenderClassificationRule::class)
            ->willReturn($repository);

        $result = (new GmailMessageClassifier($entityManager))->classify(
            'Nouvelle mission Java / React',
            'Example Jobs <Alerts@Example.com>',
            'Nous recherchons un consultant freelance. Votre profil a retenu notre attention.',
        );

        self::assertSame('JOB_ALERT', $result['category']);
        self::assertFalse($result['actionRequired']);
        self::assertStringContainsString('Correction utilisateur persistante', $result['reason']);
    }
}
