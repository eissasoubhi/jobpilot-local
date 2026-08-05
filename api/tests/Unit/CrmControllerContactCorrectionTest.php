<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\CrmController;
use App\Crm\Application\OrganizationCrmAnnotationApplier;
use App\Crm\Application\OrganizationCrmContactCorrectionApplier;
use App\Crm\Application\OrganizationCrmDirectoryBuilder;
use App\Entity\Application;
use App\Entity\CrmContactCorrection;
use App\Entity\InboxMessage;
use App\Entity\Positioning;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CrmControllerContactCorrectionTest extends TestCase
{
    public function testSavesACorrectionOnlyForAnExistingDerivedContact(): void
    {
        $positioning = (new Positioning())->fill([
            'agency' => 'Acme Consulting',
            'recruiterName' => 'Jane Recruiter',
            'recruiterEmail' => 'jane@acme.test',
            'recruiterPhone' => '+33 6 00 00 00 00',
            'missionTitle' => 'Senior Symfony Developer',
        ]);

        $emptyRepository = $this->repository();
        $emptyRepository->method('findBy')->willReturn([]);
        $positioningRepository = $this->repository();
        $positioningRepository->method('findBy')->willReturn([$positioning]);
        $correctionRepository = $this->repository();
        $correctionRepository->method('findOneBy')->with([
            'organizationKey' => 'acme consulting',
            'contactKey' => 'jane@acme.test',
        ])->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => match ($class) {
                Application::class, InboxMessage::class => $emptyRepository,
                Positioning::class => $positioningRepository,
                CrmContactCorrection::class => $correctionRepository,
                default => throw new \LogicException('Unexpected repository '.$class),
            },
        );
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static fn (object $entity): bool => $entity instanceof CrmContactCorrection
                && $entity->getOrganizationKey() === 'acme consulting'
                && $entity->getContactKey() === 'jane@acme.test'
                && $entity->getCorrectedName() === 'Jane France'
                && $entity->getCorrectedEmail() === 'jane.france@acme.test'
                && $entity->getCorrectedPhone() === '+33 6 10 20 30 40',
        ));
        $entityManager->expects(self::once())->method('flush');

        $controller = $this->controller($entityManager);
        $request = Request::create(
            '/api/crm/organizations/acme%20consulting/contacts/jane%40acme.test/correction',
            'PUT',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => ' Jane France ',
                'email' => ' JANE.FRANCE@ACME.TEST ',
                'phone' => ' +33 6 10 20 30 40 ',
            ], JSON_THROW_ON_ERROR),
        );

        $response = $controller->updateContactCorrection(
            'acme consulting',
            'jane@acme.test',
            $request,
        );
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('acme consulting', $payload['organizationKey']);
        self::assertSame('jane@acme.test', $payload['contactKey']);
        self::assertSame('Jane France', $payload['correction']['correctedName']);
        self::assertSame('jane.france@acme.test', $payload['correction']['correctedEmail']);
    }

    public function testRejectsACorrectionForAnUnknownContact(): void
    {
        $positioning = (new Positioning())->fill([
            'agency' => 'Acme Consulting',
            'recruiterName' => 'Jane Recruiter',
            'recruiterEmail' => 'jane@acme.test',
            'missionTitle' => 'Senior Symfony Developer',
        ]);

        $emptyRepository = $this->repository();
        $emptyRepository->method('findBy')->willReturn([]);
        $positioningRepository = $this->repository();
        $positioningRepository->method('findBy')->willReturn([$positioning]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => match ($class) {
                Application::class, InboxMessage::class => $emptyRepository,
                Positioning::class => $positioningRepository,
                default => throw new \LogicException('Unexpected repository '.$class),
            },
        );
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $response = $this->controller($entityManager)->updateContactCorrection(
            'acme consulting',
            'invented@acme.test',
            Request::create('/api/crm/contact/correction', 'PUT', content: '{}'),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRejectsInvalidCorrectionContent(): void
    {
        $positioning = (new Positioning())->fill([
            'agency' => 'Acme Consulting',
            'recruiterName' => 'Jane Recruiter',
            'recruiterEmail' => 'jane@acme.test',
            'missionTitle' => 'Senior Symfony Developer',
        ]);

        $emptyRepository = $this->repository();
        $emptyRepository->method('findBy')->willReturn([]);
        $positioningRepository = $this->repository();
        $positioningRepository->method('findBy')->willReturn([$positioning]);
        $correctionRepository = $this->repository();
        $correctionRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => match ($class) {
                Application::class, InboxMessage::class => $emptyRepository,
                Positioning::class => $positioningRepository,
                CrmContactCorrection::class => $correctionRepository,
                default => throw new \LogicException('Unexpected repository '.$class),
            },
        );
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $request = Request::create(
            '/api/crm/contact/correction',
            'PUT',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'not-an-email'], JSON_THROW_ON_ERROR),
        );
        $response = $this->controller($entityManager)->updateContactCorrection(
            'acme consulting',
            'jane@acme.test',
            $request,
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    private function controller(EntityManagerInterface $entityManager): CrmController
    {
        return new CrmController(
            $entityManager,
            new OrganizationCrmDirectoryBuilder(),
            new OrganizationCrmAnnotationApplier(),
            new OrganizationCrmContactCorrectionApplier(),
        );
    }

    /** @return EntityRepository<object>&MockObject */
    private function repository(): EntityRepository&MockObject
    {
        return $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findBy', 'findOneBy', 'findAll'])
            ->getMock();
    }
}
