<?php

namespace Oro\Bundle\AddressBundle\Tests\Unit\Form\Type;

use Oro\Bundle\AddressBundle\Form\Type\AddressCollectionType;
use Oro\Bundle\FormBundle\Form\Type\CollectionType;
use PHPUnit\Framework\TestCase;

class AddressCollectionTypeTest extends TestCase
{
    private AddressCollectionType $type;

    #[\Override]
    protected function setUp(): void
    {
        $this->type = new AddressCollectionType();
    }

    public function testGetParent(): void
    {
        $this->assertEquals(CollectionType::class, $this->type->getParent());
    }

    public function testGetName(): void
    {
        $this->assertEquals('oro_address_collection', $this->type->getName());
    }
}
