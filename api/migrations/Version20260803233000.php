<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Gmail automatic submission settings, recipient and tracking fields.';
    }

    public function up(Schema $schema): void
    {
        // Defaults make adding non-null columns safe for existing rows. They are
        // removed immediately because the Doctrine mapping owns future defaults.
        $this->addSql('ALTER TABLE user_settings ADD auto_submit_enabled BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE user_settings ADD auto_submit_threshold INT DEFAULT 60 NOT NULL');
        $this->addSql('ALTER TABLE user_settings ADD auto_submit_daily_limit INT DEFAULT 5 NOT NULL');
        $this->addSql('ALTER TABLE user_settings ALTER auto_submit_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE user_settings ALTER auto_submit_threshold DROP DEFAULT');
        $this->addSql('ALTER TABLE user_settings ALTER auto_submit_daily_limit DROP DEFAULT');
        $this->addSql('ALTER TABLE job_offer ADD application_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE application ADD gmail_message_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE application ADD submission_error TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE application ADD submission_attempted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_settings DROP auto_submit_enabled');
        $this->addSql('ALTER TABLE user_settings DROP auto_submit_threshold');
        $this->addSql('ALTER TABLE user_settings DROP auto_submit_daily_limit');
        $this->addSql('ALTER TABLE job_offer DROP application_email');
        $this->addSql('ALTER TABLE application DROP gmail_message_id');
        $this->addSql('ALTER TABLE application DROP submission_error');
        $this->addSql('ALTER TABLE application DROP submission_attempted_at');
    }
}
