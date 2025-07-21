<?php

namespace Oro\Bundle\LayoutBundle\Tests\Unit\Layout\Block\Type;

use Oro\Bundle\LayoutBundle\Layout\Block\Type\ConfigurableType;
use Oro\Bundle\LayoutBundle\Tests\Unit\Layout\Block\ConfigurableBlockTestCase;
use Oro\Component\Layout\Block\Type\BaseType;
use Oro\Component\Layout\LayoutFactoryBuilderInterface;

class ConfigurableTypeTest extends ConfigurableBlockTestCase
{
    /** @var ConfigurableType */
    protected $type;

    #[\Override]
    protected function initializeLayoutFactoryBuilder(LayoutFactoryBuilderInterface $layoutFactoryBuilder)
    {
        parent::initializeLayoutFactoryBuilder($layoutFactoryBuilder);

        $this->type = new ConfigurableType();
        $this->type->setName(self::TYPE_NAME)
            ->setParent(BaseType::NAME)
            ->setOptionsConfig($this->getOptionsConfig());
        $layoutFactoryBuilder->addType($this->type);
    }

    public function testSetOptionException(): void
    {
        $this->assertSetOptionException($this->type);
    }

    public function testGetNameException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Name of block type does not configured');

        (new ConfigurableType())->getName();
    }

    public function testSetNameExceptionType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Name of block type should be a string, array given');

        $this->type->setName([]);
    }

    public function testSetParentExceptionType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Name of parent block type should be a string, array given');

        $this->type->setParent([]);
    }

    public function testGetParent(): void
    {
        $this->assertEquals(BaseType::NAME, $this->type->getParent());
        $this->type->setParent('test');
        $this->assertEquals('test', $this->type->getParent());
    }
}
