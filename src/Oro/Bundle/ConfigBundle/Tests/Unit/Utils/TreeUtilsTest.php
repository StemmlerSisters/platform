<?php

namespace Oro\Bundle\ConfigBundle\Tests\Unit\Utils;

use Oro\Bundle\ConfigBundle\Config\Tree\GroupNodeDefinition;
use Oro\Bundle\ConfigBundle\Utils\TreeUtils;
use PHPUnit\Framework\TestCase;

class TreeUtilsTest extends TestCase
{
    protected static function getTestGroup(): GroupNodeDefinition
    {
        $node1 = new GroupNodeDefinition('node1', [], []);
        $node1->setLevel(1);
        $node1->setPriority(20);
        $node2 = new GroupNodeDefinition('node2', [], []);
        $node2->setLevel(2);
        $node3 = new GroupNodeDefinition('node3', [], [$node2]);
        $node3->setLevel(1);
        $node3->setPriority(10);

        $root = new GroupNodeDefinition('node4', [], [$node1, $node3]);
        $root->setLevel(0);

        return $root;
    }

    public function testFindNodeByName(): void
    {
        // existing node
        $result = TreeUtils::findNodeByName(self::getTestGroup(), 'node2');
        $this->assertEquals('node2', $result->getName());

        // not found node
        $result = TreeUtils::findNodeByName(self::getTestGroup(), 'not_existed');
        $this->assertNull($result);
    }

    public function testGetByNestingLevel(): void
    {
        // existed nested node
        $result = TreeUtils::getByNestingLevel(self::getTestGroup(), 2);
        $this->assertEquals(2, $result->getLevel());
        $this->assertEquals('node2', $result->getName());

        // not found node
        $result = TreeUtils::getByNestingLevel(self::getTestGroup(), 5);
        $this->assertNull($result);
    }

    public function testGetFirstNodeName(): void
    {
        // not empty node
        $result = TreeUtils::getFirstNodeName(self::getTestGroup());
        $this->assertEquals('node1', $result);

        // empty node
        $result = TreeUtils::getFirstNodeName(new GroupNodeDefinition('some_name'));
        $this->assertNull($result);
    }

    public function testGetConfigKey(): void
    {
        $this->assertEquals('root.name', TreeUtils::getConfigKey('root', 'name'));
        $this->assertEquals('root__name', TreeUtils::getConfigKey('root', 'name', '__'));
    }
}
