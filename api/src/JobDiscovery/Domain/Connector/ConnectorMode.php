<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

enum ConnectorMode: string
{
    case API = 'API';
    case RSS = 'RSS';
    case SCRAPING_HTTP = 'SCRAPING_HTTP';
    case SCRAPING_BROWSER = 'SCRAPING_BROWSER';
    case GMAIL = 'GMAIL';
    case EXTENSION = 'EXTENSION';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::API => 'API',
            self::RSS => 'Flux RSS',
            self::SCRAPING_HTTP => 'Scraping HTTP',
            self::SCRAPING_BROWSER => 'Scraping navigateur',
            self::GMAIL => 'Gmail',
            self::EXTENSION => 'Extension Chrome',
            self::MANUAL => 'Import manuel',
        };
    }
}
