<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add plans table, platform admin flag on users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plans (id UUID NOT NULL, code VARCHAR(50) NOT NULL, label VARCHAR(100) NOT NULL, max_sites INT NOT NULL, max_users INT NOT NULL, min_uptime_interval_seconds INT NOT NULL, webhooks_enabled BOOLEAN NOT NULL, active BOOLEAN NOT NULL, sort_order INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_plans_code ON plans (code)');
        $this->addSql('ALTER TABLE users ADD is_platform_admin BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE users ALTER is_platform_admin DROP DEFAULT');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $plans = [
            ['free', 'Free', 1, 1, 900, false, 10],
            ['basic', 'Basic', 5, 3, 300, true, 20],
            ['agency', 'Agency', 25, 10, 60, true, 30],
            ['enterprise', 'Enterprise', 1000, 1000, 60, true, 40],
        ];

        foreach ($plans as [$code, $label, $maxSites, $maxUsers, $interval, $webhooks, $sort]) {
            $id = $this->generateUuid();
            $webhooksSql = $webhooks ? 'true' : 'false';
            $this->addSql(sprintf(
                "INSERT INTO plans (id, code, label, max_sites, max_users, min_uptime_interval_seconds, webhooks_enabled, active, sort_order, created_at, updated_at) VALUES ('%s', '%s', '%s', %d, %d, %d, %s, true, %d, '%s', '%s')",
                $id,
                $code,
                $label,
                $maxSites,
                $maxUsers,
                $interval,
                $webhooksSql,
                $sort,
                $now,
                $now,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP is_platform_admin');
        $this->addSql('DROP TABLE plans');
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x70);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
