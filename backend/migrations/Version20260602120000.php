<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial MVP schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, name VARCHAR(255) NOT NULL, plan_code VARCHAR(50) NOT NULL, status VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(30) NOT NULL, api_token VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E97BA2F5EB ON users (api_token)');
        $this->addSql('CREATE TABLE organization_users (organization_id UUID NOT NULL, user_id UUID NOT NULL, role VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(organization_id, user_id))');
        $this->addSql('CREATE INDEX IDX_7D1AFA032C8A3DE ON organization_users (organization_id)');
        $this->addSql('CREATE INDEX IDX_7D1AFA0A76ED395 ON organization_users (user_id)');
        $this->addSql('CREATE TABLE sites (id UUID NOT NULL, organization_id UUID NOT NULL, domain VARCHAR(255) NOT NULL, site_url TEXT NOT NULL, status VARCHAR(30) NOT NULL, module_version VARCHAR(50) DEFAULT NULL, bitrix_version VARCHAR(50) DEFAULT NULL, php_version VARCHAR(50) DEFAULT NULL, last_heartbeat_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, config_version INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BC00AA6332C8A3DE ON sites (organization_id)');
        $this->addSql('CREATE TABLE site_keys (id UUID NOT NULL, site_id UUID NOT NULL, secret_encrypted TEXT NOT NULL, active_from TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, active_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9FAA922AF6BD1646 ON site_keys (site_id)');
        $this->addSql('ALTER TABLE organization_users ADD CONSTRAINT FK_7D1AFA032C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_users ADD CONSTRAINT FK_7D1AFA0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sites ADD CONSTRAINT FK_BC00AA6332C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE site_keys ADD CONSTRAINT FK_9FAA922AF6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_users DROP CONSTRAINT FK_7D1AFA032C8A3DE');
        $this->addSql('ALTER TABLE organization_users DROP CONSTRAINT FK_7D1AFA0A76ED395');
        $this->addSql('ALTER TABLE sites DROP CONSTRAINT FK_BC00AA6332C8A3DE');
        $this->addSql('ALTER TABLE site_keys DROP CONSTRAINT FK_9FAA922AF6BD1646');
        $this->addSql('DROP TABLE site_keys');
        $this->addSql('DROP TABLE sites');
        $this->addSql('DROP TABLE organization_users');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE organizations');
    }
}
