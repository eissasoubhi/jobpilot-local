<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\CustomScraping;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\DynamicJobSourceConnectorProvider;
use App\Service\CustomScraperExtractionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: CustomScraperDynamicConnectorProvider::class, priority: 10)]
final class AiRecoveryCustomScraperConnectorProvider implements DynamicJobSourceConnectorProvider
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomScraperExtractionService $extraction,
        private CustomScraperAiRecoveryService $recovery,
    ) {
    }

    public function connectors(): iterable
    {
        $sources = $this->em->getRepository(CustomScraperSource::class)->findBy(
            [
                'enabled' => true,
                'authorizationConfirmed' => true,
            ],
            ['name' => 'ASC'],
        );

        foreach ($sources as $source) {
            if (!$source instanceof CustomScraperSource) {
                continue;
            }
            $data = $source->toArray();
            if (!is_int($data['id'] ?? null)) {
                continue;
            }

            yield new AiRecoveryCustomScraperJobConnector(
                $source,
                $this->extraction,
                $this->recovery,
            );
        }
    }
}
