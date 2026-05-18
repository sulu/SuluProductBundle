<?php

declare(strict_types=1);

namespace Sulu\Product\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add attribute, attribute option, and product attribute tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS pr_attributes (
                id INT AUTO_INCREMENT NOT NULL,
                uuid CHAR(36) DEFAULT NULL,
                attribute_key VARCHAR(255) NOT NULL,
                type VARCHAR(32) NOT NULL DEFAULT \'number\',
                UNIQUE INDEX UNIQ_pr_attributes_uuid (uuid),
                UNIQUE INDEX UNIQ_pr_attributes_key (attribute_key),
                PRIMARY KEY (id)
            ) ENGINE = InnoDB'
        );

        $this->addSql(
            'CREATE TABLE IF NOT EXISTS pr_attribute_translations (
                id INT AUTO_INCREMENT NOT NULL,
                locale VARCHAR(5) NOT NULL,
                name VARCHAR(255) NOT NULL,
                idAttributes INT NOT NULL,
                INDEX IDX_pr_attribute_translations_locale (locale),
                CONSTRAINT FK_pr_attr_trans
                    FOREIGN KEY (idAttributes) REFERENCES pr_attributes (id) ON DELETE CASCADE,
                PRIMARY KEY (id)
            ) ENGINE = InnoDB'
        );

        $this->addSql(
            'CREATE TABLE IF NOT EXISTS pr_attribute_options (
                id INT AUTO_INCREMENT NOT NULL,
                uuid CHAR(36) DEFAULT NULL,
                attribute_option_key VARCHAR(255) NOT NULL,
                idAttributes INT NOT NULL,
                UNIQUE INDEX UNIQ_pr_attr_opt_uuid (uuid),
                UNIQUE INDEX UNIQ_pr_attr_opt_key (idAttributes, attribute_option_key),
                CONSTRAINT FK_pr_attr_opt
                    FOREIGN KEY (idAttributes) REFERENCES pr_attributes (id) ON DELETE CASCADE,
                PRIMARY KEY (id)
            ) ENGINE = InnoDB'
        );

        $this->addSql(
            'CREATE TABLE IF NOT EXISTS pr_attribute_option_translations (
                id INT AUTO_INCREMENT NOT NULL,
                locale VARCHAR(5) NOT NULL,
                name VARCHAR(255) NOT NULL,
                idAttributeOptions INT NOT NULL,
                INDEX IDX_pr_attribute_option_translations_locale (locale),
                CONSTRAINT FK_pr_attr_opt_trans
                    FOREIGN KEY (idAttributeOptions) REFERENCES pr_attribute_options (id) ON DELETE CASCADE,
                PRIMARY KEY (id)
            ) ENGINE = InnoDB'
        );

        $this->addSql(
            'CREATE TABLE IF NOT EXISTS pr_product_attributes (
                id INT AUTO_INCREMENT NOT NULL,
                attributeKey VARCHAR(255) NOT NULL,
                attributeOptionKey VARCHAR(255) DEFAULT NULL,
                number DOUBLE PRECISION DEFAULT NULL,
                text VARCHAR(255) DEFAULT NULL,
                json JSON DEFAULT NULL,
                productUuid VARCHAR(36) NOT NULL,
                idAttributes INT NOT NULL,
                idAttributeOptions INT DEFAULT NULL,
                INDEX IDX_pr_product_attributes_key (attributeKey),
                INDEX IDX_pr_product_attributes_opt_key (attributeOptionKey),
                CONSTRAINT FK_pr_prod_attr_product
                    FOREIGN KEY (productUuid) REFERENCES pr_products (uuid) ON DELETE CASCADE,
                CONSTRAINT FK_pr_prod_attr_attr
                    FOREIGN KEY (idAttributes) REFERENCES pr_attributes (id) ON DELETE CASCADE,
                CONSTRAINT FK_pr_prod_attr_opt
                    FOREIGN KEY (idAttributeOptions) REFERENCES pr_attribute_options (id) ON DELETE SET NULL,
                PRIMARY KEY (id)
            ) ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pr_product_attributes');
        $this->addSql('DROP TABLE pr_attribute_option_translations');
        $this->addSql('DROP TABLE pr_attribute_options');
        $this->addSql('DROP TABLE pr_attribute_translations');
        $this->addSql('DROP TABLE pr_attributes');
    }
}
