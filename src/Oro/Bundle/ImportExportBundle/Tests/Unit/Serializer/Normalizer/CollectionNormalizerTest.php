<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Serializer\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Oro\Bundle\ImportExportBundle\Serializer\Normalizer\CollectionNormalizer;
use Oro\Bundle\ImportExportBundle\Serializer\Serializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\SerializerInterface;

class CollectionNormalizerTest extends TestCase
{
    private Serializer&MockObject $serializer;
    private CollectionNormalizer $normalizer;

    #[\Override]
    protected function setUp(): void
    {
        $this->serializer = $this->createMock(Serializer::class);
        $this->normalizer = new CollectionNormalizer();
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testSetInvalidSerializer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Serializer must implement');

        $this->normalizer->setSerializer($this->createMock(SerializerInterface::class));
    }

    public function testSupportsNormalization(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));

        $collection = $this->createMock(Collection::class);
        $this->assertTrue($this->normalizer->supportsNormalization($collection));
    }

    /**
     * @dataProvider supportsDenormalizationDataProvider
     */
    public function testSupportsDenormalization(string $type, bool $expectedResult): void
    {
        $this->assertEquals($expectedResult, $this->normalizer->supportsDenormalization([], $type));
    }

    public function supportsDenormalizationDataProvider(): array
    {
        return [
            ['stdClass', false],
            ['ArrayCollection', true],
            [ArrayCollection::class, true],
            ['Doctrine\Common\Collections\ArrayCollection<Foo>', true],
            ['Doctrine\Common\Collections\ArrayCollection<Foo\Bar\Baz>', true],
            ['ArrayCollection<ArrayCollection<Foo\Bar\Baz>>', true],
        ];
    }

    public function testNormalize(): void
    {
        $format = null;
        $context = ['context'];

        $firstElement = $this->createMock(\stdClass::class);
        $secondElement = $this->createMock(\ArrayObject::class);
        $data = new ArrayCollection([$firstElement, $secondElement]);

        $this->serializer->expects($this->exactly(2))
            ->method('normalize')
            ->willReturnMap([
                [$firstElement, $format, $context, 'first'],
                [$secondElement, $format, $context, 'second'],
            ]);

        $this->assertEquals(
            ['first', 'second'],
            $this->normalizer->normalize($data, $format, $context)
        );
    }

    public function testDenormalizeNotArray(): void
    {
        $this->serializer->expects($this->never())
            ->method($this->anything());
        $this->assertEquals(
            new ArrayCollection(),
            $this->normalizer->denormalize('string', '')
        );
    }

    public function testDenormalizeSimple(): void
    {
        $this->serializer->expects($this->never())
            ->method($this->anything());
        $data = ['foo', 'bar'];
        $this->assertEquals(
            new ArrayCollection($data),
            $this->normalizer->denormalize($data, 'ArrayCollection', null)
        );
    }

    public function testDenormalizeWithItemType(): void
    {
        $format = null;
        $context = [];

        $fooEntity = new \stdClass();
        $barEntity = new \stdClass();

        $this->serializer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturnMap([
                ['foo', 'ItemType', $format, $context, $fooEntity],
                ['bar', 'ItemType', $format, $context, $barEntity],
            ]);

        $this->assertEquals(
            new ArrayCollection([$fooEntity, $barEntity]),
            $this->normalizer->denormalize(
                ['foo', 'bar'],
                'ArrayCollection<ItemType>',
                $format,
                $context
            )
        );
    }
}
