<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\CrmController;
use App\Crm\Application\OrganizationCrmAnnotationApplier;
use App\Crm\Application\OrganizationCrmContactCorrectionApplier;
use App\Crm\Application\OrganizationCrmDirectoryBuilder;
use App\Entity\Application;
use App\Entity\CrmOrganizationAnnotation;
use App\Entity\InboxMessage;
use App\Entity\JobOffer;
use App\Entity\Positioning;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CrmControllerAnnotationTest extends TestCase
{
    public function testSavesAnAnnotationOnlyForAnExistingDerivedOrganization(): void
    {
        $application = new Application((new JobOffer())->fill([
            'title' => 'Senior Symfony Developer',
            'company' => 'Acme Consulting',
        ]));

        $applicationRepository = $this->repository();
        $applicationRepository->method('findBy')->willReturn([$application]);
        $emptyRepository = $this->repository();
        $emptyRepository->method('findBy')->willReturn([]);
        $annotationRepository = $this->repository();
        $annotationRepository->method('findOneBy')->with(['organizationKey' => 'acme consulting'])->willReturn(null);
        $annotationRepository->method('findAll')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => match ($class) {
                Application::class => $applicationRepository,
                Positioning::class, InboxMessage::class => $emptyRepository,
                CrmOrganizationAnnotation::class => $annotationRepository,
                default => throw new \LogicException('Unexpected repository '.$class),
            },
        );
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static fn (object $entity): bool => $entity instanceof CrmOrganizationAnnotation
                && $entity->getOrganizationKey() === 'acme consulting'
                && $entity->getDisplayName() === 'ACME France'
                && $entity->getNote() === 'Relancer dans une semaine.',
        ));
        $entityManager->expects(self::once())->method('flush');

        $controller = new CrmController(
            $entityManager,
            new OrganizationCrmDirectoryBuilder(),
            new OrganizationCrmAnnotationApplier(),
            new OrganizationCrmContactCorrectionApplier(),
        );
        $request = Request::create(
            '/api/crm/organizations/acme%20consulting/annotation',
            'PUT',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'displayName' => ' ACME France ',
                'note' => ' Relancer dans une semaine. ',
            ], JSON_THROW_ON_ERROR),
        );

        $response = $controller->updateAnnotation('acme consulting', $request);
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('acme consulting', $payload['organizationKey']);
        self::assertSame('ACME France', $payload['annotation']['displayName']);
        self::assertSame('Relancer dans une semaine.', $payload['annotation']['note']);
    }

    public function testRejectsAnAnnotationForAnUnknownOrganization(): void
    {
        $emptyRepository = $this->repository();
        $emptyRepository->method('findBy')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($emptyRepository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $controller = new CrmController(
            $entityManager,
            new OrganizationCrmDirectoryBuilder(),
            new OrganizationCrmAnnotationApplier(),
            new OrganizationCrmContactCorrectionApplier(),
        );

        $response = $controller->updateAnnotation(
            'invented organization',
            Request::create('/api/crm/organizations/invented/annotation', 'PUT', content: '{}'),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
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
