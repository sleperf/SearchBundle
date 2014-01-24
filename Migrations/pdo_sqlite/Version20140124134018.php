<?php

namespace Orange\SearchBundle\Migrations\pdo_sqlite;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/01/24 01:40:21
 */
class Version20140124134018 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE orange_search_options (
                id INTEGER NOT NULL, 
                host CLOB NOT NULL, 
                port CLOB NOT NULL, 
                path CLOB NOT NULL, 
                PRIMARY KEY(id)
            )
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP TABLE orange_search_options
        ");
    }
}