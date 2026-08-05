<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

enum ConnectorComplianceStatus: string
{
    case ALLOWED = 'ALLOWED';
    case AUTHORIZED_ONLY = 'AUTHORIZED_ONLY';
    case EMAIL_OR_EXTENSION_ONLY = 'EMAIL_OR_EXTENSION_ONLY';
    case DISABLED = 'DISABLED';
    case UNDER_REVIEW = 'UNDER_REVIEW';

    public function label(): string
    {
        return match ($this) {
            self::ALLOWED => 'Collecte autorisée',
            self::AUTHORIZED_ONLY => 'Autorisation requise',
            self::EMAIL_OR_EXTENSION_ONLY => 'E-mail ou extension uniquement',
            self::DISABLED => 'Collecte interdite',
            self::UNDER_REVIEW => 'Autorisation en cours de revue',
        };
    }

    public function allowsAutomatedCollection(): bool
    {
        return match ($this) {
            self::ALLOWED, self::AUTHORIZED_ONLY => true,
            self::EMAIL_OR_EXTENSION_ONLY, self::DISABLED, self::UNDER_REVIEW => false,
        };
    }
}
