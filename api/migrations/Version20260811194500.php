<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canonical candidate fields required by browser autofill';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE candidate_profile ADD first_name VARCHAR(120) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE candidate_profile ADD last_name VARCHAR(120) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE candidate_profile ADD address_line1 VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE candidate_profile ADD address_line2 VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE candidate_profile ADD region VARCHAR(120) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE candidate_profile ADD country VARCHAR(120) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE candidate_profile ADD country_code VARCHAR(2) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE candidate_profile ADD current_job_title VARCHAR(180) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE candidate_profile ADD preferred_locations JSON NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE candidate_profile ADD technology_experience JSON NOT NULL DEFAULT '{}'");
        $this->addSql('ALTER TABLE candidate_profile ADD desired_salary INT DEFAULT NULL');
        $this->addSql('ALTER TABLE candidate_profile ADD desired_tjm INT DEFAULT NULL');
        $this->addSql('ALTER TABLE candidate_profile ADD github_url VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE candidate_profile ADD professional_urls JSON NOT NULL DEFAULT '[]'");

        $this->addSql("UPDATE candidate_profile SET first_name = split_part(trim(full_name), ' ', 1), last_name = trim(substring(trim(full_name) from length(split_part(trim(full_name), ' ', 1)) + 1)) WHERE trim(full_name) <> ''");
        $this->addSql("UPDATE candidate_profile SET github_url = portfolio_url, portfolio_url = NULL WHERE portfolio_url LIKE '%github.com/%'");

        foreach (['first_name', 'last_name', 'address_line1', 'region', 'country', 'country_code', 'current_job_title', 'preferred_locations', 'technology_experience', 'professional_urls'] as $column) {
            $this->addSql(sprintf('ALTER TABLE candidate_profile ALTER COLUMN %s DROP DEFAULT', $column));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE candidate_profile SET portfolio_url = github_url WHERE portfolio_url IS NULL AND github_url IS NOT NULL');
        $this->addSql('ALTER TABLE candidate_profile DROP first_name');
        $this->addSql('ALTER TABLE candidate_profile DROP last_name');
        $this->addSql('ALTER TABLE candidate_profile DROP address_line1');
        $this->addSql('ALTER TABLE candidate_profile DROP address_line2');
        $this->addSql('ALTER TABLE candidate_profile DROP region');
        $this->addSql('ALTER TABLE candidate_profile DROP country');
        $this->addSql('ALTER TABLE candidate_profile DROP country_code');
        $this->addSql('ALTER TABLE candidate_profile DROP current_job_title');
        $this->addSql('ALTER TABLE candidate_profile DROP preferred_locations');
        $this->addSql('ALTER TABLE candidate_profile DROP technology_experience');
        $this->addSql('ALTER TABLE candidate_profile DROP desired_salary');
        $this->addSql('ALTER TABLE candidate_profile DROP desired_tjm');
        $this->addSql('ALTER TABLE candidate_profile DROP github_url');
        $this->addSql('ALTER TABLE candidate_profile DROP professional_urls');
    }
}
