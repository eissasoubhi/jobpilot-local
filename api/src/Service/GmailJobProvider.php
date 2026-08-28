<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\SearchDiagnosticsConnector;

final class GmailJobProvider implements GovernedJobSourceConnector, SearchDiagnosticsConnector
{
    public function __construct(
        private GmailService $gmail,
        private GmailTokenStore $tokenStore,
    ) {}

    public function code(): string
    {
        return 'gmail';
    }

    public function name(): string
    {
        return 'Gmail';
    }

    public function mode(): ConnectorMode
    {
        return ConnectorMode::GMAIL;
    }

    public function policy(): ConnectorPolicy
    {
        return new ConnectorPolicy(
            ConnectorComplianceStatus::AUTHORIZED_ONLY,
            new \DateTimeImmutable('2026-08-05'),
            'Accès limité au compte connecté par OAuth avec consentement explicite de l’utilisateur et scopes minimaux.',
        );
    }

    public function isConfigured(): bool
    {
        $configuration = $this->gmail->configuration();

        return $configuration['configured']
            && $this->tokenStore->isConnected()
            && $this->gmail->hasReadPermission();
    }

    public function configurationMessage(): ?string
    {
        $configuration = $this->gmail->configuration();
        if (!$configuration['configured']) {
            return 'Configuration OAuth incomplète : '.implode(', ', $configuration['missingVariables']).'.';
        }
        if (!$this->tokenStore->isConnected()) {
            return 'Connecte Gmail depuis les paramètres pour importer les alertes et réponses.';
        }
        if (!$this->gmail->hasReadPermission()) {
            return 'Reconnecte Gmail en acceptant l’autorisation gmail.readonly.';
        }

        return 'Alertes d’emploi, propositions recruteurs et réponses aux candidatures.';
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return list<array<string, mixed>>
     */
    public function search(array $targetJobs, array $skills): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        return $this->gmail->collectJobOffers();
    }

    /** @return array<string, mixed> */
    public function searchDiagnostics(): array
    {
        $summary = $this->gmail->lastSyncSummary();
        $messagesMatched = (int) ($summary['found'] ?? 0);
        $messagesImported = (int) ($summary['imported'] ?? 0);
        $messagesAlreadyKnown = (int) ($summary['duplicates'] ?? 0);
        $messagesFailed = (int) ($summary['failed'] ?? 0);
        $offersExtracted = (int) ($summary['offersFound'] ?? 0);

        $outcome = match (true) {
            $messagesMatched === 0 => 'no_messages_matched',
            $messagesImported === 0 && $messagesFailed === 0 && $messagesAlreadyKnown > 0 => 'no_new_messages',
            $offersExtracted === 0 => 'messages_without_offers',
            default => 'offers_extracted',
        };

        return [
            'source' => 'gmail',
            'messagesMatched' => $messagesMatched,
            'messagesImported' => $messagesImported,
            'messagesAlreadyKnown' => $messagesAlreadyKnown,
            'messagesFailed' => $messagesFailed,
            'offersExtracted' => $offersExtracted,
            'messagesAssociated' => (int) ($summary['associated'] ?? 0),
            'messagesActionRequired' => (int) ($summary['actionRequired'] ?? 0),
            'outcome' => $outcome,
        ];
    }
}
