<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\Shared;

use Oro\Bundle\ApiBundle\Exception\ActionNotAllowedException;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Processor\Subresource\Shared\RecognizeAssociationType;
use Oro\Bundle\ApiBundle\Provider\SubresourcesProvider;
use Oro\Bundle\ApiBundle\Request\ApiSubresource;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\GetSubresourceProcessorTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Response;

class RecognizeAssociationTypeTest extends GetSubresourceProcessorTestCase
{
    private SubresourcesProvider&MockObject $subresourcesProvider;
    private RecognizeAssociationType $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subresourcesProvider = $this->createMock(SubresourcesProvider::class);

        $this->processor = new RecognizeAssociationType(
            $this->subresourcesProvider
        );
    }

    public function testProcessWhenEntityClassNameIsAlreadySet(): void
    {
        $this->subresourcesProvider->expects(self::never())
            ->method('getSubresource');

        $this->context->setClassName('Test\Class');
        $this->processor->process($this->context);
    }

    public function testProcessWhenAssociationNameIsEmpty(): void
    {
        $this->context->setAssociationName('');
        $this->processor->process($this->context);

        self::assertEquals(
            [
                Error::createValidationError(
                    'relationship constraint',
                    'The association name must be set in the context.'
                )
            ],
            $this->context->getErrors()
        );
    }

    public function testProcessForUnknownParentEntity(): void
    {
        $parentEntityClass = 'Test\ParentClass';
        $associationName = 'testAssociation';

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresource')
            ->with(
                $parentEntityClass,
                $associationName,
                $this->context->getVersion(),
                $this->context->getRequestType()
            )
            ->willReturn(null);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->processor->process($this->context);

        self::assertEquals(
            [
                Error::createValidationError(
                    'relationship constraint',
                    'Unsupported subresource.',
                    Response::HTTP_NOT_FOUND
                )
            ],
            $this->context->getErrors()
        );
    }

    public function testProcessForUnknownAssociation(): void
    {
        $parentEntityClass = 'Test\ParentClass';
        $associationName = 'testAssociation';

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresource')
            ->with(
                $parentEntityClass,
                $associationName,
                $this->context->getVersion(),
                $this->context->getRequestType()
            )
            ->willReturn(null);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->processor->process($this->context);

        self::assertEquals(
            [
                Error::createValidationError(
                    'relationship constraint',
                    'Unsupported subresource.',
                    Response::HTTP_NOT_FOUND
                )
            ],
            $this->context->getErrors()
        );
    }

    public function testProcessForExcludedAssociation(): void
    {
        $this->expectException(ActionNotAllowedException::class);
        $parentEntityClass = 'Test\ParentClass';
        $associationName = 'testAssociation';

        $associationSubresource = new ApiSubresource();
        $associationSubresource->setIsCollection(true);
        $associationSubresource->setTargetClassName('Test\Class');
        $associationSubresource->setExcludedActions([$this->context->getAction()]);

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresource')
            ->with(
                $parentEntityClass,
                $associationName,
                $this->context->getVersion(),
                $this->context->getRequestType()
            )
            ->willReturn($associationSubresource);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->processor->process($this->context);
    }

    public function testProcessForKnownAssociation(): void
    {
        $parentEntityClass = 'Test\ParentClass';
        $associationName = 'testAssociation';

        $associationSubresource = new ApiSubresource();
        $associationSubresource->setIsCollection(true);
        $associationSubresource->setTargetClassName('Test\Class');

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresource')
            ->with(
                $parentEntityClass,
                $associationName,
                $this->context->getVersion(),
                $this->context->getRequestType()
            )
            ->willReturn($associationSubresource);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->processor->process($this->context);

        self::assertEquals(
            $associationSubresource->getTargetClassName(),
            $this->context->getClassName()
        );
        self::assertEquals(
            $associationSubresource->isCollection(),
            $this->context->isCollection()
        );
    }

    public function testProcessForSubresourceWithEmptyTargetClass(): void
    {
        $parentEntityClass = 'Test\ParentClass';
        $associationName = 'testAssociation';

        $associationSubresource = new ApiSubresource();

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresource')
            ->with(
                $parentEntityClass,
                $associationName,
                $this->context->getVersion(),
                $this->context->getRequestType()
            )
            ->willReturn($associationSubresource);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->processor->process($this->context);

        self::assertEquals(
            [
                Error::createValidationError(
                    'relationship constraint',
                    'The target entity type cannot be recognized.'
                )
            ],
            $this->context->getErrors()
        );
    }
}
