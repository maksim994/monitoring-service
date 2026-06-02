<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add incidents and incident_events tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE incidents (id UUID NOT NULL, organization_id UUID NOT NULL, site_id UUID NOT NULL, check_type VARCHAR(80) NOT NULL, fingerprint VARCHAR(255) NOT NULL, severity VARCHAR(20) NOT NULL, status VARCHAR(30) NOT NULL, title VARCHAR(255) NOT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, muted_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_evidence_json JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_incidents_org ON incidents (organization_id)');
        $this->addSql('CREATE INDEX IDX_incidents_site ON incidents (site_id)');
        $this->addSql('CREATE INDEX IDX_incidents_status ON incidents (organization_id, status)');
        $this->addSql('CREATE UNIQUE INDEX incidents_active_unique ON incidents (site_id, check_type, fingerprint) WHERE status IN (\'open\', \'acknowledged\')');
        $this->addSql('CREATE TABLE incident_events (id UUID NOT NULL, incident_id UUID NOT NULL, type VARCHAR(80) NOT NULL, message TEXT NOT NULL, payload_json JSON NOT NULL, created_by UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_incident_events_incident ON incident_events (incident_id)');
        $this->addSql('ALTER TABLE incidents ADD CONSTRAINT FK_incidents_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incidents ADD CONSTRAINT FK_incidents_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incident_events ADD CONSTRAINT FK_incident_events_incident FOREIGN KEY (incident_id) REFERENCES incidents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident_events DROP CONSTRAINT FK_incident_events_incident');
        $this->addSql('ALTER TABLE incidents DROP CONSTRAINT FK_incidents_site');
        $this->addSql('ALTER TABLE incidents DROP CONSTRAINT FK_incidents_organization');
        $this->addSql('DROP TABLE incident_events');
        $this->addSql('DROP TABLE incidents');
    }
}
