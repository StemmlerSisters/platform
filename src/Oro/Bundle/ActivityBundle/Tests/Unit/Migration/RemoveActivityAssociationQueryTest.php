<?php

declare(strict_types=1);

namespace Oro\Bundle\ActivityBundle\Tests\Unit\Migration;

use Oro\Bundle\ActivityBundle\Migration\RemoveActivityAssociationQuery;
use PHPUnit\Framework\TestCase;

class RemoveActivityAssociationQueryTest extends TestCase
{
    public function testInitialized(): void
    {
        $query = new RemoveActivityAssociationQuery('Some\Activity', 'Some\Entity', true);
        self::assertEquals(
            'Remove association relation from Some\Activity entity to Some\Entity '
            . '(association kind: activity, relation type: manyToMany, drop relation column/table: yes, '
            . 'source table: n/a, target table: n/a).',
            $query->getDescription()
        );
    }
}
