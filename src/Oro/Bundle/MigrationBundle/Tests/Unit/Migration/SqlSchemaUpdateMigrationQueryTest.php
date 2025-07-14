<?php

namespace Oro\Bundle\MigrationBundle\Tests\Unit\Migration;

use Oro\Bundle\MigrationBundle\Migration\SchemaUpdateQuery;
use Oro\Bundle\MigrationBundle\Migration\SqlMigrationQuery;
use Oro\Bundle\MigrationBundle\Migration\SqlSchemaUpdateMigrationQuery;
use PHPUnit\Framework\TestCase;

class SqlSchemaUpdateMigrationQueryTest extends TestCase
{
    public function testIsUpdateRequired(): void
    {
        $query = new SqlSchemaUpdateMigrationQuery('ALTER TABLE');

        $this->assertInstanceOf(SqlMigrationQuery::class, $query);
        $this->assertInstanceOf(SchemaUpdateQuery::class, $query);
        $this->assertTrue($query->isUpdateRequired());
    }
}
