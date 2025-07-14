<?php

namespace Oro\Bundle\EntityMergeBundle\Tests\Unit\Metadata;

use Oro\Bundle\EntityMergeBundle\Exception\InvalidArgumentException;
use Oro\Bundle\EntityMergeBundle\Metadata\DoctrineMetadata;
use Oro\Bundle\EntityMergeBundle\Metadata\EntityMetadata;
use Oro\Bundle\EntityMergeBundle\Metadata\FieldMetadata;
use Oro\Bundle\EntityMergeBundle\Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;

class MetadataFactoryTest extends TestCase
{
    private MetadataFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new MetadataFactory();
    }

    public function testCreateEntityMetadata(): void
    {
        $options = ['foo' => 'bar'];
        $doctrineMetadata = $this->createMock(DoctrineMetadata::class);

        $metadata = $this->factory->createEntityMetadata($options, $doctrineMetadata);
        $this->assertInstanceOf(EntityMetadata::class, $metadata);
        $this->assertEquals($options, $metadata->all());
        $this->assertEquals($doctrineMetadata, $metadata->getDoctrineMetadata());
    }

    public function testCreateEntityMetadataFromArray(): void
    {
        $options = ['foo' => 'bar'];
        $doctrineMetadata = ['doctrineOption' => 'test'];

        $metadata = $this->factory->createEntityMetadata($options, $doctrineMetadata);
        $this->assertInstanceOf(EntityMetadata::class, $metadata);
        $this->assertInstanceOf(
            DoctrineMetadata::class,
            $metadata->getDoctrineMetadata()
        );
    }

    public function testCreateEntityMetadataFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            '$doctrineMetadata must be an array of "%s", but "stdClass" given.',
            DoctrineMetadata::class
        ));

        $options = ['foo' => 'bar'];
        $doctrineMetadata = new \stdClass();

        $this->factory->createEntityMetadata($options, $doctrineMetadata);
    }

    public function testCreateFieldMetadata(): void
    {
        $options = ['foo' => 'bar'];
        $doctrineMetadata = $this->createMock(DoctrineMetadata::class);

        $metadata = $this->factory->createFieldMetadata($options, $doctrineMetadata);
        $this->assertInstanceOf(FieldMetadata::class, $metadata);
        $this->assertEquals($options, $metadata->all());
        $this->assertEquals($doctrineMetadata, $metadata->getDoctrineMetadata());
    }

    public function testCreateFieldMetadataFromArray(): void
    {
        $options = ['foo' => 'bar'];
        $doctrineMetadata = ['doctrineOption' => 'test'];

        $metadata = $this->factory->createFieldMetadata($options, $doctrineMetadata);
        $this->assertInstanceOf(FieldMetadata::class, $metadata);
        $this->assertInstanceOf(
            DoctrineMetadata::class,
            $metadata->getDoctrineMetadata()
        );
    }

    public function testCreateFieldMetadataFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            '$doctrineMetadata must be an array of "%s", but "stdClass" given.',
            DoctrineMetadata::class
        ));

        $options = ['foo' => 'bar'];
        $doctrineMetadata = new \stdClass();

        $this->factory->createFieldMetadata($options, $doctrineMetadata);
    }

    public function testCreateDoctrineMetadata(): void
    {
        $options = ['foo' => 'bar'];
        $doctrineMetadata = $this->createMock(DoctrineMetadata::class);

        $metadata = $this->factory->createFieldMetadata($options, $doctrineMetadata);
        $this->assertInstanceOf(FieldMetadata::class, $metadata);
        $this->assertEquals($options, $metadata->all());
        $this->assertEquals($doctrineMetadata, $metadata->getDoctrineMetadata());
    }
}
