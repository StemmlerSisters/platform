<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Metadata;

use Oro\Bundle\EntityConfigBundle\Entity\ConfigModel;
use Oro\Bundle\EntityConfigBundle\Metadata\FieldMetadata;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\Fixture\DemoEntity;
use PHPUnit\Framework\TestCase;

class FieldMetadataTest extends TestCase
{
    private FieldMetadata $fieldMetadata;

    #[\Override]
    protected function setUp(): void
    {
        $this->fieldMetadata = new FieldMetadata(DemoEntity::class, 'name');
        $this->fieldMetadata->mode = ConfigModel::MODE_DEFAULT;
    }

    public function testSerialize(): void
    {
        $this->assertEquals($this->fieldMetadata, unserialize(serialize($this->fieldMetadata)));
    }
}
