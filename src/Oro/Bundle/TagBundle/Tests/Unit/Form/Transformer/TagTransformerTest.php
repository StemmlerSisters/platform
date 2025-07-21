<?php

namespace Oro\Bundle\TagBundle\Tests\Unit\Form\Transformer;

use Oro\Bundle\TagBundle\Entity\TagManager;
use Oro\Bundle\TagBundle\Form\Transformer\TagTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TagTransformerTest extends TestCase
{
    private TagManager&MockObject $manager;
    private TagTransformer $transformer;

    #[\Override]
    protected function setUp(): void
    {
        $this->manager = $this->createMock(TagManager::class);

        $this->transformer = new TagTransformer($this->manager);
    }

    /**
     * @dataProvider valueReverseTransformProvider
     */
    public function testReverseTransform(string $value, array $tags): void
    {
        $this->manager->expects($this->once())
            ->method('loadOrCreateTags')
            ->with($tags)
            ->willReturn([]);
        $this->transformer->reverseTransform($value);
    }

    public function valueReverseTransformProvider(): array
    {
        return [
            [
                'value' => '{"id":1,"name":"tag1"};;{"id":2,"name":"tag2"}',
                'tags'  => ['tag1', 'tag2']
            ]
        ];
    }

    /**
     * @dataProvider valueTransformProvider
     */
    public function testTransform(string $expected, array $value): void
    {
        $this->assertEquals($expected, $this->transformer->transform($value));
    }

    public function valueTransformProvider(): array
    {
        return [
            [
                'expected' => '{"id":1,"name":"tag1"};;{"id":2,"name":"tag2"}',
                'value'    => [['id' => 1, 'name' => 'tag1'], ['id' => 2, 'name' => 'tag2']],
            ]
        ];
    }
}
