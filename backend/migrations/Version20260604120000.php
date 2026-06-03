<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last snapshot fields to checks for cabinet display';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checks ADD last_status VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE checks ADD last_value_json JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE checks ADD last_collected_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checks DROP last_collected_at');
        $this->addSql('ALTER TABLE checks DROP last_value_json');
        $this->addSql('ALTER TABLE checks DROP last_status');
    }
}
