<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Shared;

use Oro\Bundle\ApiBundle\Collection\IncludedEntityCollection;
use Oro\Bundle\ApiBundle\Collection\IncludedEntityData;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Processor\Shared\AddIncludedEntitiesToResultDocument;
use Oro\Bundle\ApiBundle\Request\DocumentBuilderInterface;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\FormProcessorTestCase;

class AddIncludedEntitiesToResultDocumentTest extends FormProcessorTestCase
{
    private AddIncludedEntitiesToResultDocument $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new AddIncludedEntitiesToResultDocument();
    }

    public function testProcessWithoutIncludedData(): void
    {
        $this->context->setResponseStatusCode(200);
        $this->processor->process($this->context);
    }

    public function testProcessWithoutIncludedEntities(): void
    {
        $this->context->setIncludedData(['key' => 'value']);
        $this->context->setResponseStatusCode(200);
        $this->processor->process($this->context);
    }

    public function testProcessWithoutResponseDocumentBuilder(): void
    {
        $this->context->setIncludedData(['key' => 'value']);
        $this->context->setIncludedEntities(new IncludedEntityCollection());
        $this->context->setResponseStatusCode(200);
        $this->processor->process($this->context);
    }

    public function testProcessForSuccessResponse(): void
    {
        $documentBuilder = $this->createMock(DocumentBuilderInterface::class);
        $includedEntities = new IncludedEntityCollection();

        $normalizedData = ['normalizedKey' => 'normalizedValue'];
        $metadata = new EntityMetadata('Test\Entity');
        $entityData = new IncludedEntityData('/included/0', 0);
        $entityData->setNormalizedData($normalizedData);
        $entityData->setMetadata($metadata);
        $includedEntities->add(new \stdClass(), 'Test\Class', 'testId', $entityData);

        $documentBuilder->expects(self::once())
            ->method('addIncludedObject')
            ->with(
                self::identicalTo($normalizedData),
                $this->context->getRequestType(),
                self::identicalTo($metadata)
            );

        $this->context->setIncludedData(['key' => 'value']);
        $this->context->setIncludedEntities($includedEntities);
        $this->context->setResponseDocumentBuilder($documentBuilder);
        $this->context->setResponseStatusCode(200);
        $this->processor->process($this->context);
    }

    public function testProcessForFailureResponse(): void
    {
        $documentBuilder = $this->createMock(DocumentBuilderInterface::class);
        $includedEntities = new IncludedEntityCollection();

        $normalizedData = ['normalizedKey' => 'normalizedValue'];
        $metadata = new EntityMetadata('Test\Entity');
        $entityData = new IncludedEntityData('/included/0', 0);
        $entityData->setNormalizedData($normalizedData);
        $entityData->setMetadata($metadata);
        $includedEntities->add(new \stdClass(), 'Test\Class', 'testId', $entityData);

        $documentBuilder->expects(self::never())
            ->method('addIncludedObject');

        $this->context->setIncludedData(['key' => 'value']);
        $this->context->setIncludedEntities($includedEntities);
        $this->context->setResponseDocumentBuilder($documentBuilder);
        $this->context->setResponseStatusCode(400);
        $this->processor->process($this->context);
    }
}
