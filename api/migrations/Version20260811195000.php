<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-source search URL template and keyword configuration for custom scrapers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE custom_scraper_source ADD search_url_template VARCHAR(2048) DEFAULT NULL');
        $this->addSql("ALTER TABLE custom_scraper_source ADD search_keywords JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE custom_scraper_source ALTER search_keywords DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE custom_scraper_source DROP search_url_template');
        $this->addSql('ALTER TABLE custom_scraper_source DROP search_keywords');
    }
}
