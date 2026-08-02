<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align relation index names with Doctrine ORM metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_position_job RENAME TO IDX_2B2A70193481D195');
        $this->addSql('ALTER INDEX idx_position_cv RENAME TO IDX_2B2A7019DD417CCA');
        $this->addSql('ALTER INDEX idx_job_recommended_cv RENAME TO IDX_288A3A4EB09970FE');
        $this->addSql('ALTER INDEX idx_app_job RENAME TO IDX_A45BDDC13481D195');
        $this->addSql('ALTER INDEX idx_app_cv RENAME TO IDX_A45BDDC1DD417CCA');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_2B2A70193481D195 RENAME TO idx_position_job');
        $this->addSql('ALTER INDEX IDX_2B2A7019DD417CCA RENAME TO idx_position_cv');
        $this->addSql('ALTER INDEX IDX_288A3A4EB09970FE RENAME TO idx_job_recommended_cv');
        $this->addSql('ALTER INDEX IDX_A45BDDC13481D195 RENAME TO idx_app_job');
        $this->addSql('ALTER INDEX IDX_A45BDDC1DD417CCA RENAME TO idx_app_cv');
    }
}
