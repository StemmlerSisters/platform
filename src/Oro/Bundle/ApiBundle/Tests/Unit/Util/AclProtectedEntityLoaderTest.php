<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Util;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Metadata\FieldMetadata;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Group;
use Oro\Bundle\ApiBundle\Tests\Unit\OrmRelatedTestCase;
use Oro\Bundle\ApiBundle\Util\AclProtectedEntityLoader;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\ApiBundle\Util\EntityIdHelper;
use Oro\Bundle\ApiBundle\Util\QueryAclHelper;
use Oro\Component\DoctrineUtils\ORM\QueryHintResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class AclProtectedEntityLoaderTest extends OrmRelatedTestCase
{
    private EntityIdHelper&MockObject $entityIdHelper;
    private QueryAclHelper&MockObject $queryAclHelper;
    private QueryHintResolverInterface&MockObject $queryHintResolver;
    private AclProtectedEntityLoader $entityLoader;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->entityIdHelper = $this->createMock(EntityIdHelper::class);
        $this->queryAclHelper = $this->createMock(QueryAclHelper::class);
        $this->queryHintResolver = $this->createMock(QueryHintResolverInterface::class);

        $this->entityLoader = new AclProtectedEntityLoader(
            $this->doctrineHelper,
            $this->entityIdHelper,
            $this->queryAclHelper,
            $this->queryHintResolver
        );
    }

    public function testFindEntityWhenAccessGranted(): void
    {
        $entityClass = 'Test\Entity';
        $entityId = 1;
        $entity = new \stdClass();

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->setIdentifierFieldNames(['id']);
        $metadata->addField(new FieldMetadata('id'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);

        $this->doctrineHelper->expects(self::once())
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturn($aclProtectedQb);
        $this->entityIdHelper->expects(self::once())
            ->method('applyEntityIdentifierRestriction')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata));
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);

        $this->queryHintResolver->expects(self::never())
            ->method('resolveHints');

        self::assertSame(
            $entity,
            $this->entityLoader->findEntity($entityClass, $entityId, $config, $metadata, $requestType)
        );
    }

    public function testFindEntityWhenAccessDenied(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('No access to the entity.');

        $entityClass = Group::class;
        $entityId = 1;

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->setIdentifierFieldNames(['id']);
        $metadata->addField(new FieldMetadata('id'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);
        $notAclProtectedQb = $this->createMock(QueryBuilder::class);
        $notAclProtectedQuery = new Query($this->em);
        $notAclProtectedQuery->setDQL(sprintf('SELECT e.id FROM %s AS e', $entityClass));

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturnOnConsecutiveCalls($aclProtectedQb, $notAclProtectedQb);
        $this->entityIdHelper->expects(self::exactly(2))
            ->method('applyEntityIdentifierRestriction')
            ->withConsecutive(
                [self::identicalTo($aclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata)],
                [self::identicalTo($notAclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata)]
            );
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityIdentifierFieldNamesForClass')
            ->with($entityClass)
            ->willReturn(['id']);
        $notAclProtectedQb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('getQuery')
            ->willReturn($notAclProtectedQuery);
        $this->setQueryExpectation(
            $this->getDriverConnectionMock($this->em),
            $notAclProtectedQuery->getSQL(),
            [['id_0' => $entityId]]
        );

        $this->queryHintResolver->expects(self::never())
            ->method('resolveHints');

        $this->entityLoader->findEntity($entityClass, $entityId, $config, $metadata, $requestType);
    }

    public function testFindEntityWhenAccessDeniedAndConfigHasHints(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('No access to the entity.');

        $entityClass = Group::class;
        $entityId = 1;

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();
        $config->addHint('HINT_TEST');

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->setIdentifierFieldNames(['id']);
        $metadata->addField(new FieldMetadata('id'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);
        $notAclProtectedQb = $this->createMock(QueryBuilder::class);
        $notAclProtectedQuery = new Query($this->em);
        $notAclProtectedQuery->setDQL(sprintf('SELECT e.id FROM %s AS e', $entityClass));

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturnOnConsecutiveCalls($aclProtectedQb, $notAclProtectedQb);
        $this->entityIdHelper->expects(self::exactly(2))
            ->method('applyEntityIdentifierRestriction')
            ->withConsecutive(
                [self::identicalTo($aclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata)],
                [self::identicalTo($notAclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata)]
            );
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityIdentifierFieldNamesForClass')
            ->with($entityClass)
            ->willReturn(['id']);
        $notAclProtectedQb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('getQuery')
            ->willReturn($notAclProtectedQuery);
        $this->setQueryExpectation(
            $this->getDriverConnectionMock($this->em),
            $notAclProtectedQuery->getSQL(),
            [['id_0' => $entityId]]
        );

        $this->queryHintResolver->expects(self::once())
            ->method('resolveHints')
            ->with(self::identicalTo($notAclProtectedQuery), ['HINT_TEST']);

        $this->entityLoader->findEntity($entityClass, $entityId, $config, $metadata, $requestType);
    }

    public function testFindEntityWhenEntityNotFound(): void
    {
        $entityClass = Group::class;
        $entityId = 1;

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->setIdentifierFieldNames(['id']);
        $metadata->addField(new FieldMetadata('id'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);
        $notAclProtectedQb = $this->createMock(QueryBuilder::class);
        $notAclProtectedQuery = new Query($this->em);
        $notAclProtectedQuery->setDQL(sprintf('SELECT e.id FROM %s AS e', $entityClass));

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturnOnConsecutiveCalls($aclProtectedQb, $notAclProtectedQb);
        $this->entityIdHelper->expects(self::exactly(2))
            ->method('applyEntityIdentifierRestriction')
            ->withConsecutive(
                [self::identicalTo($aclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata)],
                [self::identicalTo($notAclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata)]
            );
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityIdentifierFieldNamesForClass')
            ->with($entityClass)
            ->willReturn(['id']);
        $notAclProtectedQb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('getQuery')
            ->willReturn($notAclProtectedQuery);
        $this->setQueryExpectation(
            $this->getDriverConnectionMock($this->em),
            $notAclProtectedQuery->getSQL(),
            []
        );

        $this->queryHintResolver->expects(self::never())
            ->method('resolveHints');

        self::assertNull(
            $this->entityLoader->findEntity($entityClass, $entityId, $config, $metadata, $requestType)
        );
    }

    public function testFindEntityWhenEntityNotFoundAndConfigHasHints(): void
    {
        $entityClass = Group::class;
        $entityId = 1;

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();
        $config->addHint('HINT_TEST');

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->setIdentifierFieldNames(['id']);
        $metadata->addField(new FieldMetadata('id'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);
        $notAclProtectedQb = $this->createMock(QueryBuilder::class);
        $notAclProtectedQuery = new Query($this->em);
        $notAclProtectedQuery->setDQL(sprintf('SELECT e.id FROM %s AS e', $entityClass));

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturnOnConsecutiveCalls($aclProtectedQb, $notAclProtectedQb);
        $this->entityIdHelper->expects(self::exactly(2))
            ->method('applyEntityIdentifierRestriction')
            ->withConsecutive(
                [self::identicalTo($aclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata)],
                [self::identicalTo($notAclProtectedQb), self::identicalTo($entityId), self::identicalTo($metadata)]
            );
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityIdentifierFieldNamesForClass')
            ->with($entityClass)
            ->willReturn(['id']);
        $notAclProtectedQb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('getQuery')
            ->willReturn($notAclProtectedQuery);
        $this->setQueryExpectation(
            $this->getDriverConnectionMock($this->em),
            $notAclProtectedQuery->getSQL(),
            []
        );

        $this->queryHintResolver->expects(self::once())
            ->method('resolveHints')
            ->with(self::identicalTo($notAclProtectedQuery), ['HINT_TEST']);

        self::assertNull(
            $this->entityLoader->findEntity($entityClass, $entityId, $config, $metadata, $requestType)
        );
    }

    public function testFindEntityByWhenAccessGranted(): void
    {
        $entityClass = 'Test\Entity';
        $criteria = ['field1' => 1];
        $entity = new \stdClass();

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('field1'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);

        $this->doctrineHelper->expects(self::once())
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturn($aclProtectedQb);
        $aclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $aclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);

        $this->queryHintResolver->expects(self::never())
            ->method('resolveHints');

        self::assertSame(
            $entity,
            $this->entityLoader->findEntityBy($entityClass, $criteria, $config, $metadata, $requestType)
        );
    }

    public function testFindEntityByWhenAccessGrantedAndWithRenamedField(): void
    {
        $entityClass = 'Test\Entity';
        $criteria = ['renamedField1' => 1];
        $entity = new \stdClass();

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('renamedField1'))->setPropertyPath('field1');

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);

        $this->doctrineHelper->expects(self::once())
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturn($aclProtectedQb);
        $aclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $aclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn($entity);

        $this->queryHintResolver->expects(self::never())
            ->method('resolveHints');

        self::assertSame(
            $entity,
            $this->entityLoader->findEntityBy($entityClass, $criteria, $config, $metadata, $requestType)
        );
    }

    public function testFindEntityByWhenCriteriaContainsUnknownField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The entity "Test\Entity" does not have metadata for the "field1" property.');

        $entityClass = 'Test\Entity';
        $criteria = ['field1' => 1];

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();

        $metadata = new EntityMetadata('Test\Entity');

        $this->doctrineHelper->expects(self::never())
            ->method('createQueryBuilder');
        $this->queryAclHelper->expects(self::never())
            ->method('protectQuery');

        $this->queryHintResolver->expects(self::never())
            ->method('resolveHints');

        $this->entityLoader->findEntityBy($entityClass, $criteria, $config, $metadata, $requestType);
    }

    public function testFindEntityByWhenAccessDenied(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('No access to the entity.');

        $entityClass = Group::class;
        $entityId = 1;
        $criteria = ['field1' => $entityId];

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('field1'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);
        $notAclProtectedQb = $this->createMock(QueryBuilder::class);
        $notAclProtectedQuery = new Query($this->em);
        $notAclProtectedQuery->setDQL(sprintf('SELECT e.id FROM %s AS e', $entityClass));

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturnOnConsecutiveCalls($aclProtectedQb, $notAclProtectedQb);
        $aclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $aclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityIdentifierFieldNamesForClass')
            ->with($entityClass)
            ->willReturn(['id']);
        $notAclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('getQuery')
            ->willReturn($notAclProtectedQuery);
        $this->setQueryExpectation(
            $this->getDriverConnectionMock($this->em),
            $notAclProtectedQuery->getSQL(),
            [['id_0' => $entityId]]
        );

        $this->queryHintResolver->expects(self::never())
            ->method('resolveHints');

        $this->entityLoader->findEntityBy($entityClass, $criteria, $config, $metadata, $requestType);
    }

    public function testFindEntityByWhenAccessDeniedAndConfigHasHints(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('No access to the entity.');

        $entityClass = Group::class;
        $entityId = 1;
        $criteria = ['field1' => $entityId];

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();
        $config->addHint('HINT_TEST');

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('field1'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);
        $notAclProtectedQb = $this->createMock(QueryBuilder::class);
        $notAclProtectedQuery = new Query($this->em);
        $notAclProtectedQuery->setDQL(sprintf('SELECT e.id FROM %s AS e', $entityClass));

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturnOnConsecutiveCalls($aclProtectedQb, $notAclProtectedQb);
        $aclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $aclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityIdentifierFieldNamesForClass')
            ->with($entityClass)
            ->willReturn(['id']);
        $notAclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('getQuery')
            ->willReturn($notAclProtectedQuery);
        $this->setQueryExpectation(
            $this->getDriverConnectionMock($this->em),
            $notAclProtectedQuery->getSQL(),
            [['id_0' => $entityId]]
        );

        $this->queryHintResolver->expects(self::once())
            ->method('resolveHints')
            ->with(self::identicalTo($notAclProtectedQuery), ['HINT_TEST']);

        $this->entityLoader->findEntityBy($entityClass, $criteria, $config, $metadata, $requestType);
    }

    public function testFindEntityByWhenEntityNotFound(): void
    {
        $entityClass = Group::class;
        $criteria = ['field1' => 1];

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('field1'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);
        $notAclProtectedQb = $this->createMock(QueryBuilder::class);
        $notAclProtectedQuery = new Query($this->em);
        $notAclProtectedQuery->setDQL(sprintf('SELECT e.id FROM %s AS e', $entityClass));

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturnOnConsecutiveCalls($aclProtectedQb, $notAclProtectedQb);
        $aclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $aclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityIdentifierFieldNamesForClass')
            ->with($entityClass)
            ->willReturn(['id']);
        $notAclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('getQuery')
            ->willReturn($notAclProtectedQuery);
        $this->setQueryExpectation(
            $this->getDriverConnectionMock($this->em),
            $notAclProtectedQuery->getSQL(),
            []
        );

        $this->queryHintResolver->expects(self::never())
            ->method('resolveHints');

        self::assertNull(
            $this->entityLoader->findEntityBy($entityClass, $criteria, $config, $metadata, $requestType)
        );
    }

    public function testFindEntityByWhenEntityNotFoundAndConfigHasHints(): void
    {
        $entityClass = Group::class;
        $criteria = ['field1' => 1];

        $requestType = new RequestType([RequestType::REST]);
        $config = new EntityDefinitionConfig();
        $config->addHint('HINT_TEST');

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('field1'));

        $aclProtectedQb = $this->createMock(QueryBuilder::class);
        $aclProtectedQuery = $this->createMock(AbstractQuery::class);
        $notAclProtectedQb = $this->createMock(QueryBuilder::class);
        $notAclProtectedQuery = new Query($this->em);
        $notAclProtectedQuery->setDQL(sprintf('SELECT e.id FROM %s AS e', $entityClass));

        $this->doctrineHelper->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with($entityClass, 'e')
            ->willReturnOnConsecutiveCalls($aclProtectedQb, $notAclProtectedQb);
        $aclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $aclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $this->queryAclHelper->expects(self::once())
            ->method('protectQuery')
            ->with(self::identicalTo($aclProtectedQb), self::identicalTo($config), self::identicalTo($requestType))
            ->willReturn($aclProtectedQuery);
        $aclProtectedQuery->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityIdentifierFieldNamesForClass')
            ->with($entityClass)
            ->willReturn(['id']);
        $notAclProtectedQb->expects(self::once())
            ->method('andWhere')
            ->with('e.field1 = :field1')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('setParameter')
            ->with('field1', 1)
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('select')
            ->with('e.id')
            ->willReturnSelf();
        $notAclProtectedQb->expects(self::once())
            ->method('getQuery')
            ->willReturn($notAclProtectedQuery);
        $this->setQueryExpectation(
            $this->getDriverConnectionMock($this->em),
            $notAclProtectedQuery->getSQL(),
            []
        );

        $this->queryHintResolver->expects(self::once())
            ->method('resolveHints')
            ->with(self::identicalTo($notAclProtectedQuery), ['HINT_TEST']);

        self::assertNull(
            $this->entityLoader->findEntityBy($entityClass, $criteria, $config, $metadata, $requestType)
        );
    }
}
