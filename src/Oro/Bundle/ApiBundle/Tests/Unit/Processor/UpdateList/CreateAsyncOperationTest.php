<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\UpdateList;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\ApiBundle\Entity\AsyncOperation;
use Oro\Bundle\ApiBundle\Processor\UpdateList\CreateAsyncOperation;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CreateAsyncOperationTest extends UpdateListProcessorTestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;

    private AuthorizationCheckerInterface&MockObject $authorizationChecker;
    private CreateAsyncOperation $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->processor = new CreateAsyncOperation($this->doctrineHelper, $this->authorizationChecker);
    }

    public function testProcessWhenAsyncOperationIsAlreadyCreated(): void
    {
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');
        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->context->setOperationId(123);
        $this->processor->process($this->context);
    }

    public function testProcessWithoutTargetFileName(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The target file name was not set to the context.');

        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');
        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->processor->process($this->context);
    }

    public function testProcessWhenNoCreatePermissionForAsyncOperation(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('No access to create the asynchronous operation.');

        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('CREATE', 'entity:' . AsyncOperation::class)
            ->willReturn(false);

        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->context->setAction('create');
        $this->context->setClassName('Test\Entity');
        $this->context->setTargetFileName('testFile');
        $this->processor->process($this->context);
    }

    public function testProcessWhenNoViewPermissionForAsyncOperation(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('No access to create the asynchronous operation.');

        $this->authorizationChecker->expects(self::exactly(2))
            ->method('isGranted')
            ->withConsecutive(
                ['CREATE', 'entity:' . AsyncOperation::class],
                ['VIEW', 'entity:' . AsyncOperation::class]
            )
            ->willReturnOnConsecutiveCalls(true, false);

        $this->doctrineHelper->expects(self::never())
            ->method('getEntityManager');

        $this->context->setAction('create');
        $this->context->setClassName('Test\Entity');
        $this->context->setTargetFileName('testFile');
        $this->processor->process($this->context);
    }

    public function testProcess(): void
    {
        $action = 'create';
        $entityClass = 'Test\Entity';
        $targetFileName = 'testFile';
        $operationId = 123;

        $this->authorizationChecker->expects(self::exactly(2))
            ->method('isGranted')
            ->withConsecutive(
                ['CREATE', 'entity:' . AsyncOperation::class],
                ['VIEW', 'entity:' . AsyncOperation::class]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityManager')
            ->willReturn($em);
        $em->expects(self::once())
            ->method('persist')
            ->willReturnCallback(
                function (AsyncOperation $entity) use ($action, $entityClass, $targetFileName, $operationId) {
                    self::assertEquals($action, $entity->getActionName());
                    self::assertEquals($entityClass, $entity->getEntityClass());
                    self::assertEquals($targetFileName, $entity->getDataFileName());
                    self::assertEquals(AsyncOperation::STATUS_NEW, $entity->getStatus());

                    ReflectionUtil::setId($entity, $operationId);
                }
            );
        $em->expects(self::once())
            ->method('flush');

        $this->context->setAction($action);
        $this->context->setClassName($entityClass);
        $this->context->setTargetFileName($targetFileName);
        $this->processor->process($this->context);

        self::assertSame($operationId, $this->context->getOperationId());
    }
}
