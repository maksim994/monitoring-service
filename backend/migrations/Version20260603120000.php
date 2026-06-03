<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add maintenance_windows table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE maintenance_windows (id UUID NOT NULL, organization_id UUID NOT NULL, site_id UUID NOT NULL, check_type VARCHAR(80) DEFAULT NULL, title VARCHAR(255) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_maintenance_windows_site ON maintenance_windows (site_id, starts_at, ends_at)');
        $this->addSql('ALTER TABLE maintenance_windows ADD CONSTRAINT FK_maintenance_windows_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE maintenance_windows ADD CONSTRAINT FK_maintenance_windows_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE maintenance_windows DROP CONSTRAINT FK_maintenance_windows_site');
        $this->addSql('ALTER TABLE maintenance_windows DROP CONSTRAINT FK_maintenance_windows_organization');
        $this->addSql('DROP TABLE maintenance_windows');
    }
}
