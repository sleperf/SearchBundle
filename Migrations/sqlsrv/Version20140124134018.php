<?php

namespace Orange\SearchBundle\Migrations\sqlsrv;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/01/24 01:40:22
 */
class Version20140124134018 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE orange_search_options (
                id INT IDENTITY NOT NULL, 
                host VARCHAR(MAX) NOT NULL, 
                port VARCHAR(MAX) NOT NULL, 
                path VARCHAR(MAX) NOT NULL, 
                PRIMARY KEY (id)
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