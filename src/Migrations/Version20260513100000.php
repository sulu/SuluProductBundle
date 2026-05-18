<?php

declare(strict_types=1);

namespace Sulu\Product\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add code to pr_products; add pr_product_translations table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pr_products ADD COLUMN IF NOT EXISTS code VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_pr_products_code ON pr_products (code)');
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS pr_product_translations (
                id INT AUTO_INCREMENT NOT NULL,
                locale VARCHAR(5) NOT NULL,
                name VARCHAR(255) NOT NULL,
                productUuid VARCHAR(36) NOT NULL,
                INDEX IDX_pr_product_translations_locale (locale),
                CONSTRAINT FK_pr_product_translations_product
                    FOREIGN KEY (productUuid) REFERENCES pr_products (uuid) ON DELETE CASCADE,
                PRIMARY KEY (id)
            ) ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pr_product_translations');
        $this->addSql('DROP INDEX UNIQ_pr_products_code ON pr_products');
        $this->addSql('ALTER TABLE pr_products DROP code');
    }
}
