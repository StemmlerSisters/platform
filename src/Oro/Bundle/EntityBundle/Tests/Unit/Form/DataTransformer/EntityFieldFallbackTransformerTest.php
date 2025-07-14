<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Form\DataTransformer;

use Oro\Bundle\EntityBundle\Entity\EntityFieldFallbackValue;
use Oro\Bundle\EntityBundle\Form\DataTransformer\EntityFieldFallbackTransformer;
use PHPUnit\Framework\TestCase;

class EntityFieldFallbackTransformerTest extends TestCase
{
    private EntityFieldFallbackTransformer $entityFieldFallbackTransformer;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityFieldFallbackTransformer = new EntityFieldFallbackTransformer();
    }

    public function testTransformReturnsValueIfNotFallbackType(): void
    {
        $this->assertEquals('testValue', $this->entityFieldFallbackTransformer->transform('testValue'));
    }

    public function testTransformSetsScalarValueIfScalar(): void
    {
        $value = new EntityFieldFallbackValue();
        $testValue = 'testValue';
        $value->setScalarValue($testValue);
        $value = $this->entityFieldFallbackTransformer->transform($value);
        $this->assertSame($testValue, $value->getScalarValue());
    }

    public function testReverseTransformClearsOwnValues(): void
    {
        $value = new EntityFieldFallbackValue();
        $value->setScalarValue('testValue');
        $value->setArrayValue(['testValue']);
        $value->setFallback('testFallback');

        $value = $this->entityFieldFallbackTransformer->reverseTransform($value);
        $this->assertNull($value->getScalarValue());
        $this->assertNull($value->getArrayValue());
        $this->assertNull($value->getOwnValue());
    }

    public function testReverseTransformClearsFallbackAndArrayIfScalar(): void
    {
        $value = new EntityFieldFallbackValue();
        $scalarValue = 'testValue';
        $value->setScalarValue($scalarValue);
        $value->setArrayValue(['testValue']);

        $value = $this->entityFieldFallbackTransformer->reverseTransform($value);
        $this->assertNull($value->getFallback());
        $this->assertEquals($scalarValue, $value->getScalarValue());
        $this->assertNull($value->getArrayValue());
        $this->assertNotNull($value->getOwnValue());
    }

    public function testReverseTransformClearsFallbackAndArrayIfArray(): void
    {
        $value = new EntityFieldFallbackValue();
        $arrayValue = ['testValue'];
        $value->setScalarValue($arrayValue);

        $value = $this->entityFieldFallbackTransformer->reverseTransform($value);
        $this->assertNull($value->getFallback());
        $this->assertEquals($arrayValue, $value->getArrayValue());
        $this->assertNull($value->getScalarValue());
        $this->assertEquals($arrayValue, $value->getOwnValue());
    }
}
