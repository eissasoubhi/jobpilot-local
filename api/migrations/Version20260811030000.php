<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve the latest generated cover letter separately from manual edits.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application ADD generated_cover_letter TEXT DEFAULT NULL');
        $this->addSql('UPDATE application SET generated_cover_letter = cover_letter');
        $this->addSql('ALTER TABLE application ALTER generated_cover_letter SET NOT NULL');
        $this->addSql('ALTER TABLE application ADD cover_letter_edited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application DROP generated_cover_letter');
        $this->addSql('ALTER TABLE application DROP cover_letter_edited_at');
    }
}
