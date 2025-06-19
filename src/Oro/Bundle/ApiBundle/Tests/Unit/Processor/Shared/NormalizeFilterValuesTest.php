<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Shared;

use Oro\Bundle\ApiBundle\Filter\ComparisonFilter;
use Oro\Bundle\ApiBundle\Filter\FieldsFilter;
use Oro\Bundle\ApiBundle\Filter\FilterInterface;
use Oro\Bundle\ApiBundle\Filter\FilterOperator;
use Oro\Bundle\ApiBundle\Filter\FilterValue;
use Oro\Bundle\ApiBundle\Filter\StringComparisonFilter;
use Oro\Bundle\ApiBundle\Metadata\AssociationMetadata;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Metadata\FieldMetadata;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Model\ErrorSource;
use Oro\Bundle\ApiBundle\Model\NotResolvedIdentifier;
use Oro\Bundle\ApiBundle\Model\Range;
use Oro\Bundle\ApiBundle\Processor\Shared\NormalizeFilterValues;
use Oro\Bundle\ApiBundle\Request\Constraint;
use Oro\Bundle\ApiBundle\Request\DataType;
use Oro\Bundle\ApiBundle\Request\EntityIdTransformerInterface;
use Oro\Bundle\ApiBundle\Request\EntityIdTransformerRegistry;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetList\GetListProcessorTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class NormalizeFilterValuesTest extends GetListProcessorTestCase
{
    private ValueNormalizer&MockObject $valueNormalizer;
    private EntityIdTransformerRegistry&MockObject $entityIdTransformerRegistry;
    private NormalizeFilterValues $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->valueNormalizer = $this->createMock(ValueNormalizer::class);
        $this->entityIdTransformerRegistry = $this->createMock(EntityIdTransformerRegistry::class);

        $this->processor = new NormalizeFilterValues(
            $this->valueNormalizer,
            $this->entityIdTransformerRegistry
        );
    }

    public function testProcessOnExistingQuery(): void
    {
        $this->context->setQuery(new \stdClass());
        $context = clone $this->context;
        $this->processor->process($this->context);
        self::assertEquals($context, $this->context);
    }

    public function testProcessForNotStandaloneFilter(): void
    {
        $filters = $this->context->getFilters();
        $filters->add('filter1', $this->createMock(FilterInterface::class));

        $this->context->getFilterValues()->set('filter1', new FilterValue('filter1', 'test'));

        $this->valueNormalizer->expects(self::never())
            ->method('normalizeValue');
        $this->entityIdTransformerRegistry->expects(self::never())
            ->method('getEntityIdTransformer');

        $this->processor->process($this->context);

        self::assertFalse($this->context->hasErrors());
    }

    public function testProcessForSpecialHandlingFilter(): void
    {
        $filters = $this->context->getFilters();
        $filters->add('filter1', new FieldsFilter('string'));

        $this->context->getFilterValues()->set('filter1', new FilterValue('filter1', 'test'));

        $this->valueNormalizer->expects(self::never())
            ->method('normalizeValue');
        $this->entityIdTransformerRegistry->expects(self::never())
            ->method('getEntityIdTransformer');

        $this->processor->process($this->context);

        self::assertFalse($this->context->hasErrors());
    }

    public function testProcessForFieldFilters(): void
    {
        $filters = $this->context->getFilters();
        $idFilter = new ComparisonFilter('integer');
        $labelFilter = new StringComparisonFilter('string');
        $nameFilter = new StringComparisonFilter('string');
        $nameFilter->setAllowEmpty(true);
        $filters->add('id', $idFilter);
        $filters->add('label', $labelFilter);
        $filters->add('name', $nameFilter);

        $this->context->getFilterValues()->set('id', new FilterValue('id', '1'));
        $this->context->getFilterValues()->set('label', new FilterValue('label', 'test_label'));
        $this->context->getFilterValues()->set('name', new FilterValue('label', 'test_name'));

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->addField(new FieldMetadata('id'));
        $metadata->addField(new FieldMetadata('label'));
        $metadata->addField(new FieldMetadata('name'));

        $requestType = $this->context->getRequestType();
        $this->valueNormalizer->expects(self::exactly(3))
            ->method('normalizeValue')
            ->willReturnMap([
                ['1', 'integer', $requestType, false, false, [], 1],
                ['test_label', 'string', $requestType, false, false, [], 'normalized_label'],
                ['test_name', 'string', $requestType, false, false, ['allow_empty' => true], 'normalized_name']
            ]);
        $this->entityIdTransformerRegistry->expects(self::never())
            ->method('getEntityIdTransformer');

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame(1, $this->context->getFilterValues()->getOne('id')->getValue());
        self::assertSame('normalized_label', $this->context->getFilterValues()->getOne('label')->getValue());
        self::assertSame('normalized_name', $this->context->getFilterValues()->getOne('name')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForFieldFilterWhenNoFieldMetadata(): void
    {
        $filters = $this->context->getFilters();
        $labelFilter = new StringComparisonFilter('string');
        $labelFilter->setField('label');
        $filters->add('label', $labelFilter);

        $this->context->getFilterValues()->set('label', new FilterValue('label', 'test_label'));

        $metadata = new EntityMetadata('Test\Entity');

        $requestType = $this->context->getRequestType();
        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('test_label', 'string', $requestType, false, false, [])
            ->willReturn('normalized_label');
        $this->entityIdTransformerRegistry->expects(self::never())
            ->method('getEntityIdTransformer');

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame('normalized_label', $this->context->getFilterValues()->getOne('label')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForEmptyValueFieldFilter(): void
    {
        $filters = $this->context->getFilters();
        $stringFilter = new ComparisonFilter('string');
        $filters->add('label', $stringFilter);

        $this->context->getFilterValues()->set('label', new FilterValue('label', 'no', FilterOperator::EMPTY_VALUE));

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('no', 'boolean', $this->context->getRequestType(), false, false, [])
            ->willReturn(false);
        $this->entityIdTransformerRegistry->expects(self::never())
            ->method('getEntityIdTransformer');

        $this->processor->process($this->context);

        self::assertFalse($this->context->getFilterValues()->getOne('label')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForSingleIdFilter(): void
    {
        $filters = $this->context->getFilters();
        $idFilter = new ComparisonFilter('integer');
        $idFilter->setField('idField');
        $filters->add('id', $idFilter);

        $this->context->getFilterValues()->set('id', new FilterValue('id', 'predefinedId'));

        $metadata = new EntityMetadata('Test\Entity');
        $metadata->setIdentifierFieldNames(['id']);
        $idField = new FieldMetadata('id');
        $idField->setPropertyPath('idField');
        $metadata->addField($idField);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId', 'string', $this->context->getRequestType(), false, false, [])
            ->willReturn('predefinedId');
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::once())
            ->method('reverseTransform')
            ->with('predefinedId', self::identicalTo($metadata))
            ->willReturn(1);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame(1, $this->context->getFilterValues()->getOne('id')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForAssociationFilter(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()->set('association', new FilterValue('association', 'predefinedId'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId', 'string', $this->context->getRequestType(), false, false, [])
            ->willReturn('predefinedId');
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::once())
            ->method('reverseTransform')
            ->with('predefinedId', self::identicalTo($associationTargetMetadata))
            ->willReturn(1);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame(1, $this->context->getFilterValues()->getOne('association')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForRenamedAssociationFilter(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('association_field');
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()->set('association', new FilterValue('association', 'predefinedId'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationMetadata->setPropertyPath('association_field');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId', 'string', $this->context->getRequestType(), false, false, [])
            ->willReturn('predefinedId');
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::once())
            ->method('reverseTransform')
            ->with('predefinedId', self::identicalTo($associationTargetMetadata))
            ->willReturn(1);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame(1, $this->context->getFilterValues()->getOne('association')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForExistsAssociationFilter(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()
            ->set('association', new FilterValue('association', 'no', FilterOperator::EXISTS));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('no', 'boolean', $this->context->getRequestType(), false, false, [])
            ->willReturn(false);
        $this->entityIdTransformerRegistry->expects(self::never())
            ->method('getEntityIdTransformer');

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertFalse($this->context->getFilterValues()->getOne('association')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForAssociationFilterWhenValueIsArray(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $associationFilter->setArrayAllowed(true);
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()
            ->set('association', new FilterValue('association', 'predefinedId1,predefinedId2'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId1,predefinedId2', 'string', $this->context->getRequestType(), true, false, [])
            ->willReturn(['predefinedId1', 'predefinedId2']);
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::exactly(2))
            ->method('reverseTransform')
            ->willReturnMap([
                ['predefinedId1', $associationTargetMetadata, 1],
                ['predefinedId2', $associationTargetMetadata, 2]
            ]);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame([1, 2], $this->context->getFilterValues()->getOne('association')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForAssociationFilterWhenValueIsRange(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $associationFilter->setRangeAllowed(true);
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()
            ->set('association', new FilterValue('association', 'predefinedId1..predefinedId2'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId1..predefinedId2', 'string', $this->context->getRequestType(), false, true, [])
            ->willReturn(new Range('predefinedId1', 'predefinedId2'));
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::exactly(2))
            ->method('reverseTransform')
            ->willReturnMap([
                ['predefinedId1', $associationTargetMetadata, 1],
                ['predefinedId2', $associationTargetMetadata, 2]
            ]);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        /** @var Range $value */
        $value = $this->context->getFilterValues()->getOne('association')->getValue();
        self::assertInstanceOf(Range::class, $value);
        self::assertSame(1, $value->getFromValue());
        self::assertSame(2, $value->getToValue());

        self::assertFalse($this->context->hasErrors());
        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForInvalidDataType(): void
    {
        $filters = $this->context->getFilters();
        $idFilter = new ComparisonFilter('integer');
        $filters->add('id', $idFilter);

        $exception = new \UnexpectedValueException('invalid data type');

        $this->context->getFilterValues()->set('id', new FilterValue('id', 'invalid'));

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('invalid', 'integer', $this->context->getRequestType(), false, false, [])
            ->willThrowException($exception);

        $this->processor->process($this->context);

        self::assertEquals('invalid', $this->context->getFilterValues()->getOne('id')->getValue());

        self::assertEquals(
            [
                Error::createValidationError(Constraint::FILTER)
                    ->setInnerException($exception)
                    ->setSource(ErrorSource::createByParameter('id'))
            ],
            $this->context->getErrors()
        );

        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForNotSupportedFilter(): void
    {
        $filters = $this->context->getFilters();
        $idFilter = new ComparisonFilter('string');
        $filters->add('label', $idFilter);

        $this->context->getFilterValues()->set('id', new FilterValue('id', '1'));
        $this->context->getFilterValues()->set('label', new FilterValue('label', 'test'));

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('test', 'string', $this->context->getRequestType(), false, false, [])
            ->willReturn('test');

        $this->processor->process($this->context);

        self::assertEquals(
            [
                Error::createValidationError(Constraint::FILTER, 'The filter is not supported.')
                    ->setSource(ErrorSource::createByParameter('id'))
            ],
            $this->context->getErrors()
        );

        self::assertSame([], $this->context->getNotResolvedIdentifiers());
    }

    public function testProcessForAssociationFilterWhenNotResolvedIntegerId(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()->set('association', new FilterValue('association', 'predefinedId'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationTargetMetadata->setIdentifierFieldNames(['id']);
        $associationTargetMetadata->addField(new FieldMetadata('id'))->setDataType(DataType::INTEGER);
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId', 'string', $this->context->getRequestType(), false, false, [])
            ->willReturn('predefinedId');
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::once())
            ->method('reverseTransform')
            ->with('predefinedId', self::identicalTo($associationTargetMetadata))
            ->willReturn(null);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame(0, $this->context->getFilterValues()->getOne('association')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertEquals(
            [
                'filters.association' => new NotResolvedIdentifier(
                    'predefinedId',
                    'AssociationTargetClass'
                )
            ],
            $this->context->getNotResolvedIdentifiers()
        );
    }

    public function testProcessForAssociationFilterWhenNotResolvedStringId(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()->set('association', new FilterValue('association', 'predefinedId'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationTargetMetadata->setIdentifierFieldNames(['id']);
        $associationTargetMetadata->addField(new FieldMetadata('id'))->setDataType(DataType::STRING);
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId', 'string', $this->context->getRequestType(), false, false, [])
            ->willReturn('predefinedId');
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::once())
            ->method('reverseTransform')
            ->with('predefinedId', self::identicalTo($associationTargetMetadata))
            ->willReturn(null);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame('', $this->context->getFilterValues()->getOne('association')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertEquals(
            [
                'filters.association' => new NotResolvedIdentifier(
                    'predefinedId',
                    'AssociationTargetClass'
                )
            ],
            $this->context->getNotResolvedIdentifiers()
        );
    }

    public function testProcessForAssociationFilterWhenNotResolvedCombinedId(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()->set('association', new FilterValue('association', 'predefinedId'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationTargetMetadata->setIdentifierFieldNames(['id1', 'id2']);
        $associationTargetMetadata->addField(new FieldMetadata('id1'))->setDataType(DataType::STRING);
        $associationTargetMetadata->addField(new FieldMetadata('id2'))->setDataType(DataType::INTEGER);
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId', 'string', $this->context->getRequestType(), false, false, [])
            ->willReturn('predefinedId');
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::once())
            ->method('reverseTransform')
            ->with('predefinedId', self::identicalTo($associationTargetMetadata))
            ->willReturn(null);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame(
            ['id1' => '', 'id2' => 0],
            $this->context->getFilterValues()->getOne('association')->getValue()
        );

        self::assertFalse($this->context->hasErrors());
        self::assertEquals(
            [
                'filters.association' => new NotResolvedIdentifier(
                    'predefinedId',
                    'AssociationTargetClass'
                )
            ],
            $this->context->getNotResolvedIdentifiers()
        );
    }

    public function testProcessForAssociationFilterWhenValueIsArrayWhenNotResolvedId(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $associationFilter->setArrayAllowed(true);
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()
            ->set('association', new FilterValue('association', 'predefinedId1,predefinedId2'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationTargetMetadata->setIdentifierFieldNames(['id']);
        $associationTargetMetadata->addField(new FieldMetadata('id'))->setDataType(DataType::INTEGER);
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId1,predefinedId2', 'string', $this->context->getRequestType(), true, false, [])
            ->willReturn(['predefinedId1', 'predefinedId2']);
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::exactly(2))
            ->method('reverseTransform')
            ->willReturnMap([
                ['predefinedId1', $associationTargetMetadata, null],
                ['predefinedId2', $associationTargetMetadata, 2]
            ]);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        self::assertSame([0, 2], $this->context->getFilterValues()->getOne('association')->getValue());

        self::assertFalse($this->context->hasErrors());
        self::assertEquals(
            [
                'filters.association' => new NotResolvedIdentifier(
                    ['predefinedId1', 'predefinedId2'],
                    'AssociationTargetClass'
                )
            ],
            $this->context->getNotResolvedIdentifiers()
        );
    }

    public function testProcessForAssociationFilterWhenValueIsRangeWhenNotResolvedFromId(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $associationFilter->setRangeAllowed(true);
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()
            ->set('association', new FilterValue('association', 'predefinedId1..predefinedId2'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationTargetMetadata->setIdentifierFieldNames(['id']);
        $associationTargetMetadata->addField(new FieldMetadata('id'))->setDataType(DataType::INTEGER);
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId1..predefinedId2', 'string', $this->context->getRequestType(), false, true, [])
            ->willReturn(new Range('predefinedId1', 'predefinedId2'));
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::exactly(2))
            ->method('reverseTransform')
            ->willReturnMap([
                ['predefinedId1', $associationTargetMetadata, null],
                ['predefinedId2', $associationTargetMetadata, 2]
            ]);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        /** @var Range $value */
        $value = $this->context->getFilterValues()->getOne('association')->getValue();
        self::assertInstanceOf(Range::class, $value);
        self::assertSame(0, $value->getFromValue());
        self::assertSame(0, $value->getToValue());

        self::assertFalse($this->context->hasErrors());
        self::assertEquals(
            [
                'filters.association' => new NotResolvedIdentifier(
                    new Range('predefinedId1', 'predefinedId2'),
                    'AssociationTargetClass'
                )
            ],
            $this->context->getNotResolvedIdentifiers()
        );
    }

    public function testProcessForAssociationFilterWhenValueIsRangeWhenNotResolvedToId(): void
    {
        $filters = $this->context->getFilters();
        $associationFilter = new ComparisonFilter('integer');
        $associationFilter->setField('associationField');
        $associationFilter->setRangeAllowed(true);
        $filters->add('association', $associationFilter);

        $this->context->getFilterValues()
            ->set('association', new FilterValue('association', 'predefinedId1..predefinedId2'));

        $metadata = new EntityMetadata('Test\Entity');
        $associationMetadata = new AssociationMetadata('associationField');
        $associationTargetMetadata = new EntityMetadata('AssociationTargetClass');
        $associationTargetMetadata->setIdentifierFieldNames(['id']);
        $associationTargetMetadata->addField(new FieldMetadata('id'))->setDataType(DataType::INTEGER);
        $associationMetadata->setTargetMetadata($associationTargetMetadata);
        $metadata->addAssociation($associationMetadata);

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with('predefinedId1..predefinedId2', 'string', $this->context->getRequestType(), false, true, [])
            ->willReturn(new Range('predefinedId1', 'predefinedId2'));
        $entityIdTransformer = $this->createMock(EntityIdTransformerInterface::class);
        $this->entityIdTransformerRegistry->expects(self::once())
            ->method('getEntityIdTransformer')
            ->with($this->context->getRequestType())
            ->willReturn($entityIdTransformer);
        $entityIdTransformer->expects(self::exactly(2))
            ->method('reverseTransform')
            ->willReturnMap([
                ['predefinedId1', $associationTargetMetadata, 1],
                ['predefinedId2', $associationTargetMetadata, null]
            ]);

        $this->context->setMetadata($metadata);
        $this->processor->process($this->context);

        /** @var Range $value */

        $value = $this->context->getFilterValues()->getOne('association')->getValue();
        self::assertInstanceOf(Range::class, $value);
        self::assertSame(0, $value->getFromValue());
        self::assertSame(0, $value->getToValue());

        self::assertFalse($this->context->hasErrors());
        self::assertEquals(
            [
                'filters.association' => new NotResolvedIdentifier(
                    new Range('predefinedId1', 'predefinedId2'),
                    'AssociationTargetClass'
                )
            ],
            $this->context->getNotResolvedIdentifiers()
        );
    }
}
