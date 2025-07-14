<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\Transformer;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\SearchBundle\Transformer\MessageTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MessageTransformerTest extends TestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;
    private MessageTransformer $transformer;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->transformer = new MessageTransformer($this->doctrineHelper);
    }

    public function testTransform(): void
    {
        $entity = new \stdClass();
        $entities = [$entity];

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityClass')
            ->with($entity)
            ->willReturn('stdClass');

        $this->doctrineHelper->expects($this->once())
            ->method('getSingleEntityIdentifier')
            ->with($entity)
            ->willReturn(48);

        $this->assertEquals(
            [['class' => 'stdClass', 'entityIds' => [48 => 48]]],
            $this->transformer->transform($entities)
        );
    }

    public function testTransformFewDifferentEntities(): void
    {
        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        $entities = [$entity1, $entity2];

        $this->doctrineHelper->expects($this->exactly(2))
            ->method('getEntityClass')
            ->withConsecutive([$this->identicalTo($entity1)], [$this->identicalTo($entity2)])
            ->willReturnOnConsecutiveCalls('stdClass1', 'stdClass2');
        $this->doctrineHelper->expects($this->exactly(2))
            ->method('getSingleEntityIdentifier')
            ->withConsecutive([$this->identicalTo($entity1)], [$this->identicalTo($entity2)])
            ->willReturnOnConsecutiveCalls(48, 54);

        $this->assertEquals(
            [
                ['class' => 'stdClass1','entityIds' => [48 => 48]],
                ['class' => 'stdClass2','entityIds' => [54 => 54]],
            ],
            $this->transformer->transform($entities)
        );
    }

    public function testTransformChunk(): void
    {
        $entitiesCount = MessageTransformer::CHUNK_SIZE * 3 + 10;
        $entities = array_fill(0, $entitiesCount, new \stdClass());

        $this->doctrineHelper->expects($this->exactly($entitiesCount))
            ->method('getEntityClass')
            ->willReturn('stdClass');

        $this->doctrineHelper->expects($this->exactly($entitiesCount))
            ->method('getSingleEntityIdentifier')
            ->willReturnCallback(function () {
                static $id = 0;

                return $id++;
            });

        $messages = $this->transformer->transform($entities);
        $this->assertCount(4, $messages);
        $this->assertCount(MessageTransformer::CHUNK_SIZE, $messages[0]['entityIds']);
        $this->assertCount(10, $messages[3]['entityIds']);

        foreach ($messages as $message) {
            $this->assertNotEmpty($message);
        }
    }

    public function testTransformChunkStrictly(): void
    {
        $entitiesCount = MessageTransformer::CHUNK_SIZE;
        $entities = array_fill(0, $entitiesCount, new \stdClass());

        $this->doctrineHelper->expects($this->exactly($entitiesCount))
            ->method('getEntityClass')
            ->willReturn('stdClass');

        $this->doctrineHelper->expects($this->exactly($entitiesCount))
            ->method('getSingleEntityIdentifier')
            ->willReturnCallback(function () {
                static $id = 0;

                return $id++;
            });

        $messages = $this->transformer->transform($entities);
        $this->assertCount(1, $messages);
        $this->assertCount(MessageTransformer::CHUNK_SIZE, $messages[0]['entityIds']);
    }

    public function testTransformEmpty(): void
    {
        $entities = [];

        $this->doctrineHelper->expects($this->never())
            ->method('getEntityMetadata');

        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifier');

        $this->assertEquals([], $this->transformer->transform($entities));
    }
}
