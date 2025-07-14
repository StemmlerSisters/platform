<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Processor;

use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Converter\DataConverterInterface;
use Oro\Bundle\ImportExportBundle\Exception\InvalidConfigurationException;
use Oro\Bundle\ImportExportBundle\Exception\RuntimeException;
use Oro\Bundle\ImportExportBundle\Processor\ExportProcessor;
use Oro\Bundle\ImportExportBundle\Serializer\SerializerInterface;
use Oro\Bundle\ImportExportBundle\Tests\Unit\Converter\Stub\EntityNameAwareDataConverter;
use Oro\Bundle\ImportExportBundle\Tests\Unit\Converter\Stub\QueryBuilderAwareDataConverter;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportProcessorTest extends TestCase
{
    protected ContextInterface&MockObject $context;
    /** @var ExportProcessor */
    protected $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->context = $this->createMock(ContextInterface::class);
        $this->context->expects(self::any())
            ->method('getConfiguration')
            ->willReturn([]);

        $this->processor = new ExportProcessor();
    }

    public function testProcess(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Serializer must be injected.');

        $entity = $this->createMock(\stdClass::class);

        $this->processor->setImportExportContext($this->context);
        $this->processor->process($entity);
    }

    public function testProcessWithDataConverter(): void
    {
        $entity = $this->createMock(\stdClass::class);
        $serializedValue = ['serialized'];
        $expectedValue = ['expected'];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('normalize')
            ->with($entity, null)
            ->willReturn($serializedValue);

        $dataConverter = $this->createMock(DataConverterInterface::class);
        $dataConverter->expects(self::once())
            ->method('convertToExportFormat')
            ->with($serializedValue)
            ->willReturn($expectedValue);

        $this->processor->setSerializer($serializer);
        $this->processor->setDataConverter($dataConverter);
        $this->processor->setImportExportContext($this->context);

        self::assertEquals($expectedValue, $this->processor->process($entity));
    }

    public function testProcessWithoutDataConverter(): void
    {
        $entity = $this->createMock(\stdClass::class);
        $expectedValue = ['expected'];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('normalize')
            ->with($entity, null)
            ->willReturn($expectedValue);

        $this->processor->setSerializer($serializer);
        $this->processor->setImportExportContext($this->context);

        self::assertEquals($expectedValue, $this->processor->process($entity));
    }

    public function testSetImportExportContextWithoutQueryBuilder(): void
    {
        $this->context->expects(self::once())
            ->method('getOption')
            ->willReturn(null);

        $dataConverter = $this->createMock(DataConverterInterface::class);
        $dataConverter->expects(self::never())
            ->method(self::anything());

        $this->processor->setDataConverter($dataConverter);
        $this->processor->setImportExportContext($this->context);
    }

    public function testSetImportExportContextWithQueryBuilderIgnored(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $this->context->expects(self::once())
            ->method('getOption')
            ->willReturn($queryBuilder);

        $dataConverter = $this->createMock(DataConverterInterface::class);
        $dataConverter->expects(self::never())
            ->method(self::anything());

        $this->processor->setDataConverter($dataConverter);
        $this->processor->setImportExportContext($this->context);
    }

    public function testSetImportExportContextWithQueryBuilder(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $this->context->expects(self::once())
            ->method('getOption')
            ->willReturn($queryBuilder);

        $dataConverter = $this->createMock(QueryBuilderAwareDataConverter::class);
        $dataConverter->expects(self::once())
            ->method('setQueryBuilder')
            ->willReturn($queryBuilder);

        $this->processor->setDataConverter($dataConverter);
        $this->processor->setImportExportContext($this->context);
    }

    public function testSetImportExportContextFailsWithInvalidQueryBuilder(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Configuration of processor contains invalid "queryBuilder" option.'
            . ' "Doctrine\ORM\QueryBuilder" type is expected, but "stdClass" is given'
        );

        $this->context->expects(self::once())
            ->method('getOption')
            ->willReturn(new \stdClass());

        $dataConverter = $this->createMock(QueryBuilderAwareDataConverter::class);
        $dataConverter->expects(self::never())
            ->method(self::anything());

        $this->processor->setDataConverter($dataConverter);
        $this->processor->setImportExportContext($this->context);
    }

    public function testSetEntityName(): void
    {
        $entityName = 'TestEntity';

        $dataConverter = $this->createMock(EntityNameAwareDataConverter::class);
        $dataConverter->expects(self::once())
            ->method('setEntityName')
            ->with($entityName);

        $this->processor->setDataConverter($dataConverter);
        $this->processor->setEntityName($entityName);
        self::assertEquals($entityName, ReflectionUtil::getPropertyValue($this->processor, 'entityName'));
    }
}
