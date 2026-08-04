<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enrich Gmail inbox messages with bodies, classifications and business associations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE inbox_message ADD recipient VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE inbox_message ADD reply_to VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD body_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD classification_reason VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD source_platform VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD action_required BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD application_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD job_offer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inbox_message ADD matched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("ALTER TABLE inbox_message ALTER recipient DROP DEFAULT");
        $this->addSql('ALTER TABLE inbox_message ALTER action_required DROP DEFAULT');
        $this->addSql('CREATE INDEX IDX_838936803E030ACD ON inbox_message (application_id)');
        $this->addSql('CREATE INDEX IDX_838936803481D195 ON inbox_message (job_offer_id)');
        $this->addSql('CREATE INDEX idx_inbox_received ON inbox_message (received_at)');
        $this->addSql('CREATE INDEX idx_inbox_category ON inbox_message (category)');
        $this->addSql('CREATE INDEX idx_inbox_action ON inbox_message (action_required, processed)');
        $this->addSql('ALTER TABLE inbox_message ADD CONSTRAINT FK_838936803E030ACD FOREIGN KEY (application_id) REFERENCES application (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inbox_message ADD CONSTRAINT FK_838936803481D195 FOREIGN KEY (job_offer_id) REFERENCES job_offer (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inbox_message DROP CONSTRAINT FK_838936803E030ACD');
        $this->addSql('ALTER TABLE inbox_message DROP CONSTRAINT FK_838936803481D195');
        $this->addSql('DROP INDEX IDX_838936803E030ACD');
        $this->addSql('DROP INDEX IDX_838936803481D195');
        $this->addSql('DROP INDEX idx_inbox_received');
        $this->addSql('DROP INDEX idx_inbox_category');
        $this->addSql('DROP INDEX idx_inbox_action');
        $this->addSql('ALTER TABLE inbox_message DROP recipient');
        $this->addSql('ALTER TABLE inbox_message DROP reply_to');
        $this->addSql('ALTER TABLE inbox_message DROP body_text');
        $this->addSql('ALTER TABLE inbox_message DROP classification_reason');
        $this->addSql('ALTER TABLE inbox_message DROP source_platform');
        $this->addSql('ALTER TABLE inbox_message DROP action_required');
        $this->addSql('ALTER TABLE inbox_message DROP application_id');
        $this->addSql('ALTER TABLE inbox_message DROP job_offer_id');
        $this->addSql('ALTER TABLE inbox_message DROP matched_at');
    }
}
