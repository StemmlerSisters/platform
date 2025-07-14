<?php

namespace Oro\Bundle\CacheBundle\Tests\Unit\Generator;

use Oro\Bundle\CacheBundle\Generator\ObjectCacheDataSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

class ObjectCacheDataSerializerTest extends TestCase
{
    private SerializerInterface&MockObject $serializer;
    private ObjectCacheDataSerializer $dataSerializer;

    #[\Override]
    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->dataSerializer = new ObjectCacheDataSerializer($this->serializer);
    }

    public function testConvertToString(): void
    {
        $object = new \stdClass();
        $scope = 'someScope';
        $expectedResult = 'serialized_data';
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($object, 'json', ['groups' => [$scope]])
            ->willReturn($expectedResult);
        $result = $this->dataSerializer->convertToString($object, $scope);
        self::assertEquals($expectedResult, $result);
    }
}
