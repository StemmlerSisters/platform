<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApiCollection;

use Doctrine\Common\Collections\Collection;

/**
 * @group regression
 * @dbIsolationPerTest
 */
class ManyToManyLazyWithOrphanRemovalTest extends AbstractManyToManyCollectionTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures([
            '@OroApiBundle/Tests/Functional/DataFixtures/collection/mtm_lazy_with_orphan_removal.yml'
        ]);
    }

    #[\Override]
    protected function isOrphanRemoval(): bool
    {
        return true;
    }

    #[\Override]
    protected function getAssociationName(): string
    {
        return 'manyToManyLazyWithOrphanRemovalItems';
    }

    #[\Override]
    protected function getItems($entity): Collection
    {
        return $entity->getManyToManyLazyWithOrphanRemovalItems();
    }
}
