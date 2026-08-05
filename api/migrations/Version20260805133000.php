<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist connector compliance status, review metadata and collection limits.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE source_connector ADD compliance_status VARCHAR(32) DEFAULT 'UNDER_REVIEW' NOT NULL");
        $this->addSql('ALTER TABLE source_connector ALTER compliance_status DROP DEFAULT');
        $this->addSql('ALTER TABLE source_connector ADD compliance_reviewed_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE source_connector ADD compliance_note TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE source_connector ADD max_requests_per_sync INT DEFAULT NULL');
        $this->addSql('ALTER TABLE source_connector ADD daily_quota INT DEFAULT NULL');
        $this->addSql('ALTER TABLE source_connector ADD minimum_delay_milliseconds INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE source_connector ALTER minimum_delay_milliseconds DROP DEFAULT');
        $this->addSql('ALTER TABLE source_connector ADD respects_robots_txt BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE source_connector ALTER respects_robots_txt DROP DEFAULT');
        $this->addSql('CREATE INDEX idx_source_connector_compliance ON source_connector (compliance_status)');

        $this->addSql("UPDATE source_connector SET compliance_status = 'ALLOWED', compliance_reviewed_at = '2026-08-05', compliance_note = 'API publique sans authentification.', max_requests_per_sync = 5 WHERE code = 'arbeitnow'");
        $this->addSql("UPDATE source_connector SET compliance_status = 'AUTHORIZED_ONLY', compliance_reviewed_at = '2026-08-05', compliance_note = 'API officielle nécessitant des identifiants développeur.', max_requests_per_sync = 6 WHERE code = 'adzuna'");
        $this->addSql("UPDATE source_connector SET compliance_status = 'AUTHORIZED_ONLY', compliance_reviewed_at = '2026-08-05', compliance_note = 'Accès OAuth au compte Gmail connecté par l’utilisateur.' WHERE code = 'gmail'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_source_connector_compliance');
        $this->addSql('ALTER TABLE source_connector DROP compliance_status');
        $this->addSql('ALTER TABLE source_connector DROP compliance_reviewed_at');
        $this->addSql('ALTER TABLE source_connector DROP compliance_note');
        $this->addSql('ALTER TABLE source_connector DROP max_requests_per_sync');
        $this->addSql('ALTER TABLE source_connector DROP daily_quota');
        $this->addSql('ALTER TABLE source_connector DROP minimum_delay_milliseconds');
        $this->addSql('ALTER TABLE source_connector DROP respects_robots_txt');
    }
}
