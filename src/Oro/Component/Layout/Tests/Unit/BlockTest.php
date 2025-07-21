<?php

namespace Oro\Component\Layout\Tests\Unit;

use Oro\Component\Layout\Block;
use Oro\Component\Layout\BlockTypeHelperInterface;
use Oro\Component\Layout\BlockTypeInterface;
use Oro\Component\Layout\DataAccessorInterface;
use Oro\Component\Layout\LayoutContext;
use Oro\Component\Layout\RawLayout;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BlockTest extends TestCase
{
    private RawLayout $rawLayout;
    private BlockTypeHelperInterface&MockObject $typeHelper;
    private LayoutContext $context;
    private DataAccessorInterface&MockObject $data;
    private Block $block;

    #[\Override]
    protected function setUp(): void
    {
        $this->rawLayout = new RawLayout();
        $this->typeHelper = $this->createMock(BlockTypeHelperInterface::class);
        $this->context = new LayoutContext();
        $this->data = $this->createMock(DataAccessorInterface::class);

        $this->block = new Block(
            $this->rawLayout,
            $this->typeHelper,
            $this->context,
            $this->data
        );
    }

    public function testGetTypeHelper(): void
    {
        $this->assertSame($this->typeHelper, $this->block->getTypeHelper());
    }

    public function testGetContext(): void
    {
        $this->assertSame($this->context, $this->block->getContext());
    }

    public function testGetData(): void
    {
        $this->assertSame($this->data, $this->block->getData());
    }

    public function testInitialize(): void
    {
        $id = 'test_id';

        $this->block->initialize($id);

        $this->assertEquals($id, $this->block->getId());
    }

    public function testGetTypeName(): void
    {
        $id = 'test_id';
        $name = 'test_name';

        $this->rawLayout->add($id, null, $name);

        $this->block->initialize($id);

        $this->assertEquals($name, $this->block->getTypeName());
    }

    public function testGetTypeNameWhenBlockTypeIsAddedAsObject(): void
    {
        $id = 'test_id';
        $name = 'test_name';

        $type = $this->createMock(BlockTypeInterface::class);
        $type->expects($this->once())
            ->method('getName')
            ->willReturn($name);

        $this->rawLayout->add($id, null, $type);

        $this->block->initialize($id);

        $this->assertEquals($name, $this->block->getTypeName());
    }

    public function testGetAliases(): void
    {
        $id = 'test_id';

        $this->rawLayout->add($id, null, 'test_name');
        $this->rawLayout->addAlias('alias1', $id);
        $this->rawLayout->addAlias('alias2', 'alias1');

        $this->block->initialize($id);

        $this->assertEquals(['alias1', 'alias2'], $this->block->getAliases());
    }

    public function testGetParent(): void
    {
        $this->rawLayout->add('root', null, 'root');
        $this->rawLayout->add('header', 'root', 'header');
        $this->rawLayout->add('logo', 'header', 'logo');

        $this->block->initialize('logo');
        $this->assertNotNull($this->block->getParent());
        $this->assertEquals('header', $this->block->getParent()->getId());
        $this->assertNotNull($this->block->getParent()->getParent());
        $this->assertEquals('root', $this->block->getParent()->getParent()->getId());
        $this->assertNull($this->block->getParent()->getParent()->getParent());

        $this->block->initialize('header');
        $this->assertNotNull($this->block->getParent());
        $this->assertEquals('root', $this->block->getParent()->getId());
        $this->assertNull($this->block->getParent()->getParent());
    }

    public function testGetOptions(): void
    {
        $this->rawLayout->add('root', null, 'root', ['root_option1' => 'val1']);
        $this->rawLayout->setProperty(
            'root',
            RawLayout::RESOLVED_OPTIONS,
            ['root_option1' => 'val1', 'id' => 'root']
        );
        $this->rawLayout->add('header', 'root', 'header', ['header_option1' => 'val1']);
        $this->rawLayout->setProperty(
            'header',
            RawLayout::RESOLVED_OPTIONS,
            ['header_option1' => 'val1', 'id' => 'header']
        );

        $this->block->initialize('header');

        $this->assertEquals(
            ['header_option1' => 'val1', 'id' => 'header'],
            $this->block->getOptions()
        );
        $this->assertEquals(
            ['root_option1' => 'val1', 'id' => 'root'],
            $this->block->getParent()->getOptions()
        );
    }
}
