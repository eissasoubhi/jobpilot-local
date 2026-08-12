<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812004600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align reusable_answer indexes with the Doctrine mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_REUSABLE_ANSWER_CATEGORY');
        $this->addSql('ALTER INDEX UNIQ_REUSABLE_ANSWER_KEY RENAME TO UNIQ_5DEE260F7F8A65D1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX UNIQ_5DEE260F7F8A65D1 RENAME TO UNIQ_REUSABLE_ANSWER_KEY');
        $this->addSql('CREATE INDEX IDX_REUSABLE_ANSWER_CATEGORY ON reusable_answer (category)');
    }
}
