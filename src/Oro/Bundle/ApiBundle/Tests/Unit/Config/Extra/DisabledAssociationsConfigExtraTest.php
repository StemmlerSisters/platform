<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Config\Extra;

use Oro\Bundle\ApiBundle\Config\Extra\DisabledAssociationsConfigExtra;
use PHPUnit\Framework\TestCase;

class DisabledAssociationsConfigExtraTest extends TestCase
{
    private DisabledAssociationsConfigExtra $extra;

    #[\Override]
    protected function setUp(): void
    {
        $this->extra = new DisabledAssociationsConfigExtra();
    }

    public function testGetName(): void
    {
        self::assertEquals(DisabledAssociationsConfigExtra::NAME, $this->extra->getName());
    }

    public function testIsPropagable(): void
    {
        self::assertFalse($this->extra->isPropagable());
    }

    public function testCacheKeyPart(): void
    {
        self::assertEquals(DisabledAssociationsConfigExtra::NAME, $this->extra->getCacheKeyPart());
    }
}
