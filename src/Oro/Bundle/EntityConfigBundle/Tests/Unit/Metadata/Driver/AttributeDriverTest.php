<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Metadata\Driver;

use Oro\Bundle\EntityConfigBundle\Metadata\Driver\AttributeDriver;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\Fixture\EntityForAttributeTests;
use Oro\Component\PhpUtils\Attribute\Reader\AttributeReader;
use PHPUnit\Framework\TestCase;

class AttributeDriverTest extends TestCase
{
    public function testLoadMetadataForClass(): void
    {
        $driver = new AttributeDriver(new AttributeReader());

        $metadata = $driver->loadMetadataForClass(new \ReflectionClass(EntityForAttributeTests::class));

        $this->assertEquals('test_route_name', $metadata->routeName);
        $this->assertEquals('test_route_view', $metadata->routeView);
        $this->assertEquals('test_route_create', $metadata->routeCreate);
        $this->assertEquals(['custom' => 'test_route_custom'], $metadata->routes);
        $this->assertEquals('default', $metadata->mode);
        $this->assertEquals(
            [
                'ownership' => [
                    'owner_type'        => 'USER',
                    'owner_field_name'  => 'owner',
                    'owner_column_name' => 'user_owner_id',
                ]
            ],
            $metadata->defaultValues
        );

        $this->assertCount(2, $metadata->fieldMetadata);
        $idFieldMetadata = $metadata->fieldMetadata['id'];
        $this->assertEquals('id', $idFieldMetadata->name);
        $this->assertNull($idFieldMetadata->mode);
        $this->assertNull($idFieldMetadata->defaultValues);

        $nameFieldMetadata = $metadata->fieldMetadata['name'];

        $this->assertEquals('name', $nameFieldMetadata->name);
        $this->assertEquals('default', $nameFieldMetadata->mode);
        $this->assertEquals(
            [
                'email' => [
                    'available_in_template' => true,
                ]
            ],
            $nameFieldMetadata->defaultValues
        );
    }

    public function testLoadMetadataForClassForNonConfigurableEntity(): void
    {
        $driver = new AttributeDriver(new AttributeReader());

        $metadata = $driver->loadMetadataForClass(new \ReflectionClass(\stdClass::class));

        $this->assertNull($metadata);
    }
}
