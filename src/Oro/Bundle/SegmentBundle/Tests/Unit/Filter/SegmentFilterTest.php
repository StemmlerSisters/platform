<?php

namespace Oro\Bundle\SegmentBundle\Tests\Unit\Filter;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;
use Oro\Bundle\FilterBundle\Datasource\Orm\OrmFilterDatasourceAdapter;
use Oro\Bundle\FilterBundle\Filter\FilterUtility;
use Oro\Bundle\FilterBundle\Form\Type\Filter\ChoiceFilterType;
use Oro\Bundle\FilterBundle\Form\Type\Filter\EntityFilterType;
use Oro\Bundle\FilterBundle\Form\Type\Filter\FilterType;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\SubQueryLimitHelper;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Bundle\SegmentBundle\Entity\Manager\SegmentManager;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Entity\SegmentSnapshot;
use Oro\Bundle\SegmentBundle\Entity\SegmentType;
use Oro\Bundle\SegmentBundle\Filter\SegmentFilter;
use Oro\Bundle\SegmentBundle\Provider\EntityNameProvider;
use Oro\Bundle\SegmentBundle\Query\DynamicSegmentQueryBuilder;
use Oro\Bundle\SegmentBundle\Query\SegmentQueryBuilderRegistry;
use Oro\Bundle\SegmentBundle\Query\StaticSegmentQueryBuilder;
use Oro\Bundle\SegmentBundle\Tests\Unit\Stub\Entity\CmsUser;
use Oro\Bundle\TranslationBundle\Form\Extension\TranslatableChoiceTypeExtension;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Testing\Unit\ORM\OrmTestCase;
use Oro\Component\Testing\Unit\PreloadedExtension;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentFilterTest extends OrmTestCase
{
    use EntityTrait;

    private const TEST_FIELD_NAME = 't1.id';
    private const TEST_PARAM_VALUE = '%test%';

    private FormFactoryInterface $formFactory;
    private ManagerRegistry&MockObject $doctrine;
    private DynamicSegmentQueryBuilder&MockObject $dynamicSegmentQueryBuilder;
    private StaticSegmentQueryBuilder&MockObject $staticSegmentQueryBuilder;
    private EntityNameProvider&MockObject $entityNameProvider;
    private ConfigProvider&MockObject $entityConfigProvider;
    private ConfigProvider&MockObject $extendConfigProvider;
    private EntityManagerInterface&MockObject $em;
    private SubQueryLimitHelper&MockObject $subQueryLimitHelper;
    private LoggerInterface&MockObject $logger;
    private SegmentFilter $filter;

    #[\Override]
    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::any())
            ->method('trans')
            ->willReturnArgument(0);

        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->doctrine->expects(self::any())
            ->method('getManagerForClass')
            ->willReturn($this->em);

        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addExtensions(
                [
                    new PreloadedExtension(
                        [
                            'oro_type_filter' => new FilterType($translator),
                            'oro_type_choice_filter' => new ChoiceFilterType($translator),
                            'entity' => new EntityType($this->doctrine),
                            'oro_type_entity_filter' => new EntityFilterType($translator),
                        ],
                        []
                    ),
                    new CsrfExtension(
                        $this->createMock(CsrfTokenManagerInterface::class)
                    )
                ]
            )
            ->addTypeExtension(new TranslatableChoiceTypeExtension())
            ->getFormFactory();

        $this->em->expects(self::any())
            ->method('getClassMetadata')
            ->willReturn($this->getClassMetadata());

        $this->dynamicSegmentQueryBuilder = $this->createMock(DynamicSegmentQueryBuilder::class);
        $this->staticSegmentQueryBuilder = $this->createMock(StaticSegmentQueryBuilder::class);

        $this->entityNameProvider = $this->createMock(EntityNameProvider::class);
        $this->entityNameProvider->expects(self::any())
            ->method('getEntityName')
            ->willReturn('Namespace\Entity');

        $this->entityConfigProvider = $this->createMock(ConfigProvider::class);
        $this->extendConfigProvider = $this->createMock(ConfigProvider::class);

        $configManager = $this->createMock(ConfigManager::class);
        $this->entityConfigProvider->expects(self::any())
            ->method('getConfigManager')
            ->willReturn($configManager);
        $configManager->expects(self::any())
            ->method('getEntityManager')
            ->willReturn($this->em);

        $queryBuilderRegistry = new SegmentQueryBuilderRegistry();
        $queryBuilderRegistry->addQueryBuilder('static', $this->staticSegmentQueryBuilder);
        $queryBuilderRegistry->addQueryBuilder('dynamic', $this->dynamicSegmentQueryBuilder);
        $this->subQueryLimitHelper = $this->createMock(SubQueryLimitHelper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $segmentManager = new SegmentManager(
            $this->doctrine,
            $queryBuilderRegistry,
            $this->subQueryLimitHelper,
            $this->createMock(AclHelper::class),
            $this->logger
        );

        $this->filter = new SegmentFilter(
            $this->formFactory,
            new FilterUtility(),
            $this->doctrine,
            $segmentManager,
            $this->entityNameProvider,
            $this->entityConfigProvider,
            $this->extendConfigProvider
        );
        $this->filter->init('segment', ['entity' => '']);
    }

    private function getClassMetadata(): ClassMetadata
    {
        $classMetaData = $this->createMock(ClassMetadata::class);
        $classMetaData->expects(self::any())
            ->method('getName')
            ->willReturn(Segment::class);
        $classMetaData->expects(self::any())
            ->method('getIdentifier')
            ->willReturn(['id']);
        $classMetaData->expects(self::any())
            ->method('getIdentifierFieldNames')
            ->willReturn(['id']);
        $classMetaData->expects(self::any())
            ->method('getSingleIdentifierFieldName')
            ->willReturn('id');
        $classMetaData->expects(self::any())
            ->method('getTypeOfField')
            ->willReturn('integer');

        return $classMetaData;
    }

    public function testGetMetadata(): void
    {
        $activeClassName = Segment::class;
        $newClassName = 'Test\NewEntity';
        $deletedClassName = 'Test\DeletedEntity';
        $entityConfigIds = [
            new EntityConfigId('entity', $activeClassName),
            new EntityConfigId('entity', $newClassName),
            new EntityConfigId('entity', $deletedClassName),
        ];

        $this->entityConfigProvider->expects(self::once())
            ->method('getIds')
            ->willReturn($entityConfigIds);
        $this->extendConfigProvider->expects(self::any())
            ->method('getConfig')
            ->willReturnMap([
                [
                    $activeClassName,
                    null,
                    $this->createExtendConfig($activeClassName, ExtendScope::STATE_ACTIVE)
                ],
                [
                    $newClassName,
                    null,
                    $this->createExtendConfig($newClassName, ExtendScope::STATE_NEW)
                ],
                [
                    $deletedClassName,
                    null,
                    $this->createExtendConfig($deletedClassName, ExtendScope::STATE_DELETE)
                ],
            ]);

        $this->prepareRepo();
        $metadata = $this->filter->getMetadata();

        self::assertTrue(isset($metadata['entity_ids']));
        self::assertEquals(
            [$activeClassName => 'id'],
            $metadata['entity_ids']
        );
    }

    private function createExtendConfig(string $className, string $state): Config
    {
        $configId = new EntityConfigId('extend', $className);
        $config = new Config($configId);
        $config->set('state', $state);

        return $config;
    }

    private function prepareRepo()
    {
        $query = $this->createMock(AbstractQuery::class);

        $query->expects(self::any())
            ->method('execute')
            ->willReturn([]);
        $query->expects(self::any())
            ->method('getSQL')
            ->willReturn('SQL QUERY');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('where')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->willReturnSelf();
        $qb->expects(self::any())
            ->method('getParameters')
            ->willReturn(new ArrayCollection());
        $qb->expects(self::any())
            ->method('getQuery')
            ->willReturn($query);

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $this->em->expects(self::any())
            ->method('getRepository')
            ->with(Segment::class)
            ->willReturn($repo);
    }

    public function testGetForm(): void
    {
        $this->prepareRepo();
        $form = $this->filter->getForm();
        self::assertInstanceOf(FormInterface::class, $form);
    }

    public function testApplyInvalidData(): void
    {
        $dsMock = $this->createMock(FilterDatasourceAdapterInterface::class);
        $result = $this->filter->apply($dsMock, [null]);

        self::assertFalse($result);
    }

    public function testStaticApply(): void
    {
        $staticSegmentStub = $this->getEntity(Segment::class, ['id' => 1]);
        $staticSegmentStub->setType(new SegmentType(SegmentType::TYPE_STATIC));
        $staticSegmentStub->setEntity(CmsUser::class);

        $filterData = ['value' => $staticSegmentStub];

        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder()
            ->select(['t1.name'])
            ->from(\Oro\Bundle\SegmentBundle\Entity\CmsUser::class, 't1');

        $queryBuilder = new QueryBuilder($em);
        $queryBuilder->select(['ts1.id'])
            ->from(SegmentSnapshot::class, 'ts1')
            ->andWhere('ts1.segmentId = :segment')
            ->setParameter('segment', self::TEST_PARAM_VALUE);

        $ds = new OrmFilterDatasourceAdapter($qb);

        $this->staticSegmentQueryBuilder->expects(self::once())
            ->method('getQueryBuilder')
            ->with($staticSegmentStub)
            ->willReturn($queryBuilder);

        $this->filter->init('someName', [FilterUtility::DATA_NAME_KEY => self::TEST_FIELD_NAME]);
        $this->filter->apply($ds, $filterData);

        $expectedResult = 'SELECT t1.name'
            . ' FROM Oro\Bundle\SegmentBundle\Entity\CmsUser t1'
            . ' WHERE t1.id IN('
            . 'SELECT ts1.id'
            . ' FROM Oro\Bundle\SegmentBundle\Entity\SegmentSnapshot ts1'
            . ' WHERE ts1.segmentId = :_s1_segment)';

        self::assertEquals($expectedResult, $ds->getQueryBuilder()->getDQL());

        $params = $ds->getQueryBuilder()->getParameters();

        self::assertCount(1, $params, 'Should pass params to main query builder');
        self::assertEquals(self::TEST_PARAM_VALUE, $params[0]->getValue());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $em = $this->getTestEntityManager();
        $em->getConfiguration()->setMetadataDriverImpl(new AttributeDriver([]));

        return $em;
    }

    public function testDynamicApplyWithoutLimit(): void
    {
        $dynamicSegment = $this->getEntity(Segment::class, ['id' => 1]);
        $dynamicSegment->setType(new SegmentType(SegmentType::TYPE_DYNAMIC))
            ->setEntity(CmsUser::class);

        $filterData = ['value' => $dynamicSegment];

        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder()
            ->select(['t1.name'])
            ->from(\Oro\Bundle\SegmentBundle\Entity\CmsUser::class, 't1');

        $queryBuilder = new QueryBuilder($em);
        $queryBuilder->select(['ts1.id'])
            ->from(SegmentSnapshot::class, 'ts1')
            ->andWhere('ts1.segmentId = :segment')
            ->setParameter('segment', self::TEST_PARAM_VALUE);

        $ds = new OrmFilterDatasourceAdapter($qb);

        $this->dynamicSegmentQueryBuilder->expects(self::once())
            ->method('getQueryBuilder')
            ->with($dynamicSegment)
            ->willReturn($queryBuilder);

        $this->filter->init('someName', [FilterUtility::DATA_NAME_KEY => self::TEST_FIELD_NAME]);
        $this->filter->apply($ds, $filterData);

        $expectedResult = 'SELECT t1.name'
            . ' FROM Oro\Bundle\SegmentBundle\Entity\CmsUser t1'
            . ' WHERE t1.id IN('
            . 'SELECT ts1.id'
            . ' FROM Oro\Bundle\SegmentBundle\Entity\SegmentSnapshot ts1'
            . ' WHERE ts1.segmentId = :_s1_segment)';

        self::assertEquals($expectedResult, $ds->getQueryBuilder()->getDQL());

        $params = $ds->getQueryBuilder()->getParameters();

        self::assertCount(1, $params, 'Should pass params to main query builder');
        self::assertEquals(self::TEST_PARAM_VALUE, $params[0]->getValue());
    }

    public function testDynamicApplyWithLimit(): void
    {
        $dynamicSegment = $this->getEntity(Segment::class, ['id' => 1]);
        $dynamicSegment->setType(new SegmentType(SegmentType::TYPE_DYNAMIC))
            ->setEntity(CmsUser::class)
            ->setRecordsLimit(10);

        $filterData = ['value' => $dynamicSegment];

        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder()
            ->select(['t1.name'])
            ->from(\Oro\Bundle\SegmentBundle\Entity\CmsUser::class, 't1');

        $queryBuilder = new QueryBuilder($em);
        $queryBuilder->select(['ts1.id'])
            ->from(SegmentSnapshot::class, 'ts1')
            ->andWhere('ts1.segmentId = :segment')
            ->setParameter('segment', self::TEST_PARAM_VALUE);

        $ds = new OrmFilterDatasourceAdapter($qb);

        $this->dynamicSegmentQueryBuilder->expects(self::once())
            ->method('getQueryBuilder')
            ->with($dynamicSegment)
            ->willReturn($queryBuilder);

        $this->subQueryLimitHelper->expects(self::once())
            ->method('setLimit')
            ->with($queryBuilder, 10, 'id')
            ->willReturn($queryBuilder);

        $this->filter->init('someName', [FilterUtility::DATA_NAME_KEY => self::TEST_FIELD_NAME]);
        $this->filter->apply($ds, $filterData);

        $expectedResult = 'SELECT t1.name'
            . ' FROM Oro\Bundle\SegmentBundle\Entity\CmsUser t1'
            . ' WHERE t1.id IN('
            . 'SELECT ts1.id'
            . ' FROM Oro\Bundle\SegmentBundle\Entity\SegmentSnapshot ts1'
            . ' WHERE ts1.segmentId = :_s1_segment)';

        self::assertEquals($expectedResult, $ds->getQueryBuilder()->getDQL());

        $params = $ds->getQueryBuilder()->getParameters();

        self::assertCount(1, $params, 'Should pass params to main query builder');
        self::assertEquals(self::TEST_PARAM_VALUE, $params[0]->getValue());
    }

    public function testPrepareDataWithoutValue(): void
    {
        $data = [];

        $this->em->expects(self::never())
            ->method('find');

        self::assertSame($data, $this->filter->prepareData($data));
    }

    public function testPrepareDataWithNullValue(): void
    {
        $data = ['value' => null];

        $this->em->expects(self::never())
            ->method('find');

        self::assertSame($data, $this->filter->prepareData($data));
    }

    public function testPrepareDataWithSegmentIdValue(): void
    {
        $data = ['value' => 123];

        $segment = $this->createMock(Segment::class);
        $this->em->expects(self::once())
            ->method('find')
            ->with(Segment::class, $data['value'])
            ->willReturn($segment);

        self::assertSame(['value' => $segment], $this->filter->prepareData($data));
    }
}
