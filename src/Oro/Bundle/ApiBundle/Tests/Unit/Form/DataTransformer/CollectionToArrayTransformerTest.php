<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Form\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\ApiBundle\Form\DataTransformer\CollectionToArrayTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\DataTransformerInterface;

class CollectionToArrayTransformerTest extends TestCase
{
    private DataTransformerInterface&MockObject $elementTransformer;

    #[\Override]
    protected function setUp(): void
    {
        $this->elementTransformer = $this->createMock(DataTransformerInterface::class);
    }

    public function testTransform(): void
    {
        $transformer = new CollectionToArrayTransformer($this->elementTransformer);
        self::assertNull($transformer->transform(new ArrayCollection()));
    }

    /**
     * @dataProvider reverseTransformDataProvider
     */
    public function testReverseTransform(array|string|null $value, ArrayCollection $expected): void
    {
        $this->elementTransformer->expects(self::any())
            ->method('reverseTransform')
            ->willReturnCallback(function ($element) {
                return 'transformed_' . $element;
            });

        $transformer = new CollectionToArrayTransformer($this->elementTransformer);
        self::assertEquals($expected, $transformer->reverseTransform($value));
    }

    /**
     * @dataProvider reverseTransformDataProvider
     */
    public function testReverseTransformWhenUseCollectionFalse(
        array|string|null $value,
        ArrayCollection $expected
    ): void {
        $this->elementTransformer->expects(self::any())
            ->method('reverseTransform')
            ->willReturnCallback(function ($element) {
                return 'transformed_' . $element;
            });

        $transformer = new CollectionToArrayTransformer($this->elementTransformer, false);
        self::assertSame($expected->toArray(), $transformer->reverseTransform($value));
    }

    public function reverseTransformDataProvider(): array
    {
        return [
            [null, new ArrayCollection()],
            ['', new ArrayCollection()],
            [[], new ArrayCollection()],
            [['element1', 'element2'], new ArrayCollection(['transformed_element1', 'transformed_element2'])]
        ];
    }
}
