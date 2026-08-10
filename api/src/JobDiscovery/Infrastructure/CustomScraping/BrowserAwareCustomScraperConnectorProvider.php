<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\CustomScraping;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\DynamicJobSourceConnectorProvider;
use App\Service\CustomScraperExtractionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: AiRecoveryCustomScraperConnectorProvider::class, priority: 20)]
final class BrowserAwareCustomScraperConnectorProvider implements DynamicJobSourceConnectorProvider
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomScraperExtractionService $extraction,
        private CustomScraperAiRecoveryService $aiRecovery,
        private CustomScraperBrowserRecoveryService $browserRecovery,
    ) {
    }

    public function connectors(): iterable
    {
        $sources = $this->em->getRepository(CustomScraperSource::class)->findBy([], ['name' => 'ASC']);
        foreach ($sources as $source) {
            if (!$source instanceof CustomScraperSource) {
                continue;
            }
            $data = $source->toArray();
            if (!is_int($data['id'] ?? null)) {
                continue;
            }

            yield new BrowserAwareCustomScraperJobConnector(
                $source,
                $this->extraction,
                $this->aiRecovery,
                $this->browserRecovery,
            );
        }
    }
}
