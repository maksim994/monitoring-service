<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit_logs and notification_deliveries tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_logs (id UUID NOT NULL, organization_id UUID NOT NULL, actor_user_id UUID DEFAULT NULL, action VARCHAR(80) NOT NULL, target_type VARCHAR(80) NOT NULL, target_id VARCHAR(255) DEFAULT NULL, message TEXT NOT NULL, payload_json JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_audit_logs_org ON audit_logs (organization_id, created_at)');
        $this->addSql('CREATE TABLE notification_deliveries (id UUID NOT NULL, organization_id UUID NOT NULL, channel_id UUID NOT NULL, incident_id UUID DEFAULT NULL, status VARCHAR(30) NOT NULL, attempt INT NOT NULL, error TEXT DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_notification_deliveries_org ON notification_deliveries (organization_id, created_at)');
        $this->addSql('CREATE INDEX IDX_notification_deliveries_channel ON notification_deliveries (channel_id, created_at)');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_audit_logs_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_deliveries ADD CONSTRAINT FK_notification_deliveries_organization FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_deliveries ADD CONSTRAINT FK_notification_deliveries_channel FOREIGN KEY (channel_id) REFERENCES notification_channels (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_deliveries DROP CONSTRAINT FK_notification_deliveries_channel');
        $this->addSql('ALTER TABLE notification_deliveries DROP CONSTRAINT FK_notification_deliveries_organization');
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_audit_logs_organization');
        $this->addSql('DROP TABLE notification_deliveries');
        $this->addSql('DROP TABLE audit_logs');
    }
}
