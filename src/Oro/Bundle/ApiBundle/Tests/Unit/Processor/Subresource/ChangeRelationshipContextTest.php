<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource;

use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeRelationshipContext;
use Oro\Bundle\ApiBundle\Provider\ConfigProvider;
use Oro\Bundle\ApiBundle\Provider\MetadataProvider;
use PHPUnit\Framework\TestCase;

class ChangeRelationshipContextTest extends TestCase
{
    private ChangeRelationshipContext $context;

    #[\Override]
    protected function setUp(): void
    {
        $this->context = new ChangeRelationshipContext(
            $this->createMock(ConfigProvider::class),
            $this->createMock(MetadataProvider::class)
        );
    }

    public function testInitialExisting(): void
    {
        self::assertTrue($this->context->isExisting());
    }

    public function testParentEntity(): void
    {
        self::assertNull($this->context->getParentEntity());
        self::assertFalse($this->context->hasParentEntity());

        $entity = new \stdClass();
        $this->context->setParentEntity($entity);
        self::assertSame($entity, $this->context->getParentEntity());
        self::assertTrue($this->context->hasParentEntity());

        $this->context->setParentEntity(null);
        self::assertNull($this->context->getParentEntity());
        self::assertFalse($this->context->hasParentEntity());
    }
}
