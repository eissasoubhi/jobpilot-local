<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\CustomScraping;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\DynamicJobSourceConnectorProvider;
use App\Service\CustomScraperExtractionService;
use Doctrine\ORM\EntityManagerInterface;

final class CustomScraperDynamicConnectorProvider implements DynamicJobSourceConnectorProvider
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomScraperExtractionService $extraction,
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

            yield new CustomScraperJobConnector($source, $this->extraction);
        }
    }
}
