<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add checks, check_results and notification_channels';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE checks (id UUID NOT NULL, organization_id UUID NOT NULL, site_id UUID NOT NULL, type VARCHAR(80) NOT NULL, enabled BOOLEAN NOT NULL, interval_seconds INT NOT NULL, settings_json JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_checks_org_site ON checks (organization_id, site_id)');
        $this->addSql('CREATE INDEX IDX_checks_enabled ON checks (enabled, interval_seconds)');
        $this->addSql('CREATE TABLE check_results (id UUID NOT NULL, check_id UUID NOT NULL, site_id UUID NOT NULL, status VARCHAR(30) NOT NULL, value_json JSON NOT NULL, consecutive_failures INT NOT NULL, probe_id VARCHAR(64) DEFAULT NULL, checked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_check_results_check ON check_results (check_id, checked_at)');
        $this->addSql('CREATE INDEX IDX_check_results_site ON check_results (site_id, checked_at)');
        $this->addSql('CREATE TABLE notification_channels (id UUID NOT NULL, organization_id UUID NOT NULL, type VARCHAR(30) NOT NULL, name VARCHAR(255) NOT NULL, settings_json JSON NOT NULL, enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_notification_channels_org ON notification_channels (organization_id)');
        $this->addSql('ALTER TABLE checks ADD CONSTRAINT FK_checks_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE checks ADD CONSTRAINT FK_checks_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE check_results ADD CONSTRAINT FK_check_results_check FOREIGN KEY (check_id) REFERENCES checks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE check_results ADD CONSTRAINT FK_check_results_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_channels ADD CONSTRAINT FK_notification_channels_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_channels DROP CONSTRAINT FK_notification_channels_org');
        $this->addSql('ALTER TABLE check_results DROP CONSTRAINT FK_check_results_site');
        $this->addSql('ALTER TABLE check_results DROP CONSTRAINT FK_check_results_check');
        $this->addSql('ALTER TABLE checks DROP CONSTRAINT FK_checks_site');
        $this->addSql('ALTER TABLE checks DROP CONSTRAINT FK_checks_organization');
        $this->addSql('DROP TABLE notification_channels');
        $this->addSql('DROP TABLE check_results');
        $this->addSql('DROP TABLE checks');
    }
}
