<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\EventListener;

use Oro\Bundle\EntityBundle\Event\EntityStructureOptionsEvent;
use Oro\Bundle\EntityBundle\EventListener\EntityAliasStructureOptionsListener;
use Oro\Bundle\EntityBundle\Model\EntityStructure;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EntityAliasStructureOptionsListenerTest extends TestCase
{
    use EntityTrait;

    private EntityAliasResolver&MockObject $entityAliasResolver;
    private EntityAliasStructureOptionsListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityAliasResolver = $this->createMock(EntityAliasResolver::class);
        $this->listener = new EntityAliasStructureOptionsListener($this->entityAliasResolver);
    }

    /**
     * @dataProvider onOptionsRequestDataProvider
     */
    public function testOnOptionsRequest(bool $hasAlias): void
    {
        $alias = 'ALIAS';
        $pluralAlias = 'PLURAL_ALIAS';
        $entityStructure = $this->getEntity(EntityStructure::class, ['className' => \stdClass::class]);

        $this->entityAliasResolver->expects($this->once())
            ->method('hasAlias')
            ->with(\stdClass::class)
            ->willReturn($hasAlias);

        $this->entityAliasResolver->expects($this->exactly((int)$hasAlias))
            ->method('getAlias')
            ->with(\stdClass::class)
            ->willReturn($alias);

        $this->entityAliasResolver->expects($this->exactly((int)$hasAlias))
            ->method('getPluralAlias')
            ->with(\stdClass::class)
            ->willReturn($pluralAlias);

        $event = $this->getEntity(EntityStructureOptionsEvent::class, ['data' => [$entityStructure]]);
        $expectedEntityStructure = $this->getEntity(
            EntityStructure::class,
            [
                'className' => \stdClass::class,
                'alias' => $hasAlias ? $alias : null,
                'pluralAlias' => $hasAlias ? $pluralAlias : null,
            ]
        );
        $this->listener->onOptionsRequest($event);
        $this->assertEquals([$expectedEntityStructure], $event->getData());
    }

    public function onOptionsRequestDataProvider(): array
    {
        return [
            'positive' => [true],
            'negative' => [false],
        ];
    }
}
