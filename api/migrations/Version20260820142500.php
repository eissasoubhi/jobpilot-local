<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820142500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist daily, weekly and monthly application goals in user settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE user_settings
            ADD application_goals JSON NOT NULL DEFAULT '{"daily":0,"weekly":0,"monthly":0,"timezone":"Europe/Paris"}'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_settings DROP application_goals');
    }
}
