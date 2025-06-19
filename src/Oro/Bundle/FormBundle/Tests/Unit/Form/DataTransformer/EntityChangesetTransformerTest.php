<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Form\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\FormBundle\Form\DataTransformer\EntityChangesetTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class EntityChangesetTransformerTest extends TestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;
    private EntityChangesetTransformer $transformer;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);

        $this->transformer = new EntityChangesetTransformer($this->doctrineHelper, \stdClass::class);
    }

    public function testTransform(): void
    {
        $data = ['some random data'];
        $this->assertEquals($data, $this->transformer->transform($data));
    }

    /**
     * @dataProvider transformDataProvider
     */
    public function testReverseTransform(mixed $expected, mixed $value): void
    {
        if (!$expected) {
            $expected = new ArrayCollection();
        }

        $this->doctrineHelper->expects($expected->isEmpty() ? $this->never() : $this->exactly($expected->count()))
            ->method('getEntityReference')
            ->willReturnCallback(function () {
                return $this->createDataObject(func_get_arg(1));
            });

        $this->assertEquals($expected, $this->transformer->reverseTransform($value));
    }

    public function transformDataProvider(): array
    {
        return [
            [null,[]],
            [[],[]],
            [
                new ArrayCollection([
                    '1' => ['entity' => $this->createDataObject(1), 'data' => ['test' => '123', 'test2' => 'val']],
                    '2' => ['entity' => $this->createDataObject(2), 'data' => ['test' => '12']]
                ]),
                new ArrayCollection([
                    '1' => ['data' => ['test' => '123', 'test2' => 'val']],
                    '2' => ['data' => ['test' => '12']]
                ])
            ]
        ];
    }

    public function testReverseTransformException(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage('Expected argument of type "array", "string" given');

        $this->transformer->reverseTransform('test');
    }

    private function createDataObject(int $id): \stdClass
    {
        $obj = new \stdClass();
        $obj->id = $id;

        return $obj;
    }
}
