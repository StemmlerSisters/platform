<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Provider;

use Oro\Bundle\ApiBundle\Provider\AssociationAccessExclusionProviderInterface;
use Oro\Bundle\ApiBundle\Provider\ChainAssociationAccessExclusionProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChainAssociationAccessExclusionProviderTest extends TestCase
{
    /** @var AssociationAccessExclusionProviderInterface[]&MockObject[] */
    private array $providers = [];
    private ChainAssociationAccessExclusionProvider $chainProvider;

    #[\Override]
    protected function setUp(): void
    {
        $highPriorityProvider = $this->createMock(AssociationAccessExclusionProviderInterface::class);
        $lowPriorityProvider = $this->createMock(AssociationAccessExclusionProviderInterface::class);
        $this->providers = [$highPriorityProvider, $lowPriorityProvider];

        $this->chainProvider = new ChainAssociationAccessExclusionProvider($this->providers);
    }

    public function testIsIgnoreAssociationAccessCheckByLowPriorityProvider(): void
    {
        $entityClass = 'Test\Entity';
        $associationName = 'association1';

        $this->providers[0]->expects(self::once())
            ->method('isIgnoreAssociationAccessCheck')
            ->with($entityClass, $associationName)
            ->willReturn(true);
        $this->providers[1]->expects(self::never())
            ->method('isIgnoreAssociationAccessCheck');

        self::assertTrue($this->chainProvider->isIgnoreAssociationAccessCheck($entityClass, $associationName));
    }

    public function testIsIgnoreAssociationAccessCheckByHighPriorityProvider(): void
    {
        $entityClass = 'Test\Entity';
        $associationName = 'association1';

        $this->providers[0]->expects(self::once())
            ->method('isIgnoreAssociationAccessCheck')
            ->with($entityClass, $associationName)
            ->willReturn(false);
        $this->providers[1]->expects(self::once())
            ->method('isIgnoreAssociationAccessCheck')
            ->with($entityClass, $associationName)
            ->willReturn(true);

        self::assertTrue($this->chainProvider->isIgnoreAssociationAccessCheck($entityClass, $associationName));
    }

    public function testIsIgnoreAssociationAccessCheckNone(): void
    {
        $entityClass = 'Test\Entity';
        $associationName = 'association1';

        $this->providers[0]->expects(self::once())
            ->method('isIgnoreAssociationAccessCheck')
            ->with($entityClass, $associationName)
            ->willReturn(false);
        $this->providers[1]->expects(self::once())
            ->method('isIgnoreAssociationAccessCheck')
            ->with($entityClass, $associationName)
            ->willReturn(false);

        self::assertFalse($this->chainProvider->isIgnoreAssociationAccessCheck($entityClass, $associationName));
    }
}
