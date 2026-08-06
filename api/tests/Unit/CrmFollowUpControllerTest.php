<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\CrmFollowUpController;
use App\Crm\Application\OrganizationCrmDirectoryBuilder;
use App\Entity\Application;
use App\Entity\CrmFollowUpTask;
use App\Entity\InboxMessage;
use App\Entity\Positioning;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CrmFollowUpControllerTest extends TestCase
{
    public function testCreatesFollowUpForExistingDerivedContact(): void
    {
        $positioning = (new Positioning())->fill([
            'agency' => 'Acme Consulting',
            'recruiterName' => 'Jane Recruiter',
            'recruiterEmail' => 'jane@acme.test',
            'missionTitle' => 'Senior Symfony Developer',
        ]);
        $empty = $this->repository();
        $empty->method('findBy')->willReturn([]);
        $positionings = $this->repository();
        $positionings->method('findBy')->willReturn([$positioning]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn (string $class): EntityRepository => match ($class) {
            Application::class, InboxMessage::class => $empty,
            Positioning::class => $positionings,
            default => throw new \LogicException('Unexpected repository '.$class),
        });
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn (object $entity): bool => $entity instanceof CrmFollowUpTask
                && $entity->getOrganizationKey() === 'acme consulting'
                && $entity->getContactKey() === 'jane@acme.test'
                && $entity->getTitle() === 'Relancer Jane'
                && $entity->getDueAt()->format('Y-m-d') === '2026-08-12',
        ));
        $em->expects(self::once())->method('flush');

        $request = Request::create('/api/crm/organizations/acme/follow-ups', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contactKey' => 'jane@acme.test',
            'title' => ' Relancer Jane ',
            'note' => 'Demander une date de décision.',
            'dueAt' => '2026-08-12',
        ], JSON_THROW_ON_ERROR));
        $response = (new CrmFollowUpController($em, new OrganizationCrmDirectoryBuilder()))->create('acme consulting', $request);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Relancer Jane', $payload['title']);
        self::assertFalse($payload['completed']);
    }

    public function testRejectsUnknownContactAndInvalidDate(): void
    {
        $positioning = (new Positioning())->fill([
            'agency' => 'Acme Consulting',
            'recruiterEmail' => 'jane@acme.test',
            'missionTitle' => 'Symfony',
        ]);
        $empty = $this->repository();
        $empty->method('findBy')->willReturn([]);
        $positionings = $this->repository();
        $positionings->method('findBy')->willReturn([$positioning]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn (string $class): EntityRepository => match ($class) {
            Application::class, InboxMessage::class => $empty,
            Positioning::class => $positionings,
            default => throw new \LogicException('Unexpected repository '.$class),
        });
        $em->expects(self::never())->method('persist');

        $controller = new CrmFollowUpController($em, new OrganizationCrmDirectoryBuilder());
        $unknown = Request::create('/x', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contactKey' => 'unknown@acme.test', 'title' => 'Relancer', 'dueAt' => '2026-08-12',
        ], JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_NOT_FOUND, $controller->create('acme consulting', $unknown)->getStatusCode());

        $invalid = Request::create('/x', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'title' => 'Relancer', 'dueAt' => '12/08/2026',
        ], JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $controller->create('acme consulting', $invalid)->getStatusCode());
    }

    /** @return EntityRepository<object>&MockObject */
    private function repository(): EntityRepository&MockObject
    {
        return $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findBy', 'find'])
            ->getMock();
    }
}
