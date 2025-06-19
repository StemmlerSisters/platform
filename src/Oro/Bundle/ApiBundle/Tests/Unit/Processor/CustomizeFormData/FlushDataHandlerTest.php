<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\CustomizeFormData;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\ApiBundle\Collection\AdditionalEntityCollection;
use Oro\Bundle\ApiBundle\Collection\IncludedEntityCollection;
use Oro\Bundle\ApiBundle\Collection\IncludedEntityData;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\CustomizeFormDataContext;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\CustomizeFormDataEventDispatcher;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\FlushDataHandler;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\FlushDataHandlerContext;
use Oro\Bundle\ApiBundle\Processor\FormContext;
use Oro\Bundle\ApiBundle\Processor\Shared\JsonApi\SetOperationFlags;
use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeRelationshipContext;
use Oro\Component\ChainProcessor\ParameterBag;
use Oro\Component\ChainProcessor\ProcessorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class FlushDataHandlerTest extends TestCase
{
    private CustomizeFormDataEventDispatcher&MockObject $customizeFormDataEventDispatcher;
    private ProcessorInterface&MockObject $formErrorsCollector;
    private ProcessorInterface&MockObject $formErrorsCollectorForSubresource;
    private LoggerInterface&MockObject $logger;
    private FlushDataHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->customizeFormDataEventDispatcher = $this->createMock(CustomizeFormDataEventDispatcher::class);
        $this->formErrorsCollector = $this->createMock(ProcessorInterface::class);
        $this->formErrorsCollectorForSubresource = $this->createMock(ProcessorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new FlushDataHandler(
            $this->customizeFormDataEventDispatcher,
            $this->formErrorsCollector,
            $this->formErrorsCollectorForSubresource,
            $this->logger
        );
    }

    private function getForm(): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $formConfig = $this->createMock(FormConfigInterface::class);
        $form->expects(self::any())
            ->method('getConfig')
            ->willReturn($formConfig);

        return $form;
    }

    private function getFormContext(
        ?FormInterface $form,
        ?IncludedEntityCollection $itemIncludedEntities = null,
        bool $noHasErrorsExpectation = false
    ): FormContext&MockObject {
        $context = $this->createMock(FormContext::class);
        $context->expects(self::any())
            ->method('getForm')
            ->willReturn($form);
        $context->expects(self::any())
            ->method('getIncludedEntities')
            ->willReturn($itemIncludedEntities);
        $context->expects(self::any())
            ->method('getClassName')
            ->willReturn(\stdClass::class);
        if (!$noHasErrorsExpectation) {
            $context->expects(self::any())
                ->method('hasErrors')
                ->willReturn(false);
        }

        $additionalEntityCollection = new AdditionalEntityCollection();
        $context->expects(self::any())
            ->method('getAdditionalEntityCollection')
            ->willReturn($additionalEntityCollection);
        $context->expects(self::any())
            ->method('addAdditionalEntity')
            ->willReturnCallback(function ($entity) use ($additionalEntityCollection) {
                $additionalEntityCollection->add($entity);
            });
        $context->expects(self::any())
            ->method('addAdditionalEntityToRemove')
            ->willReturnCallback(function ($entity) use ($additionalEntityCollection) {
                $additionalEntityCollection->add($entity, true);
            });

        return $context;
    }

    private function getSubresourceFormContext(
        ?FormInterface $form,
        ?IncludedEntityCollection $itemIncludedEntities = null
    ): FormContext {
        $context = $this->createMock(ChangeRelationshipContext::class);
        $context->expects(self::any())
            ->method('getForm')
            ->willReturn($form);
        $context->expects(self::any())
            ->method('getIncludedEntities')
            ->willReturn($itemIncludedEntities);
        $context->expects(self::any())
            ->method('getClassName')
            ->willReturn(\stdClass::class);
        $context->expects(self::any())
            ->method('hasErrors')
            ->willReturn(false);

        return $context;
    }

    private function expectsFlush(
        array &$calls,
        EntityManagerInterface&MockObject $em,
        ?\Throwable $exception = null
    ): void {
        $connection = $this->createMock(Connection::class);

        $em->expects(self::any())
            ->method('getConnection')
            ->willReturnCallback(function () use (&$calls, $connection) {
                $calls[] = 'getConnection';

                return $connection;
            });

        $connection->expects(self::any())
            ->method('beginTransaction')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'beginTransaction';
            });
        $em->expects(self::any())
            ->method('persist')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'persist entity';
            });
        $em->expects(self::any())
            ->method('remove')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'remove entity';
            });
        $em->expects(self::any())
            ->method('flush')
            ->willReturnCallback(function () use (&$calls, $exception) {
                $calls[] = 'flushData';

                if (null !== $exception) {
                    throw $exception;
                }
            });
        $connection->expects(self::any())
            ->method('commit')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'commitTransaction';
            });
        $connection->expects(self::any())
            ->method('rollBack')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'rollbackTransaction';
            });
    }

    private function flushDataWithException(
        EntityManagerInterface $em,
        array $entityContexts,
        \Throwable $exception
    ): void {
        try {
            $this->handler->flushData(
                $em,
                new FlushDataHandlerContext($entityContexts, new ParameterBag())
            );
            self::fail('The flush exception should be raised.');
        } catch (\Throwable $e) {
            self::assertSame($exception, $e, sprintf('Exception: %s', $e->getMessage()));
        }
    }

    public function testFlushDataWhenNoError(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2);

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $form1, $form2) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext([$entityContext1, $entityContext2], new ParameterBag())
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'flushData',
                'post_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'post_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'commitTransaction',
                'post_save_data - entity 1',
                'collectFormErrors - entity 1',
                'post_save_data - entity 2',
                'collectFormErrors - entity 2'
            ],
            $calls
        );
    }

    public function testFlushDataWhenNoErrorForSubresource(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getSubresourceFormContext($form1);
        $form2 = $this->getForm();
        $entityContext2 = $this->getSubresourceFormContext($form2);

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $form1, $form2) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollectorForSubresource->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrorsForSubresource - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext([$entityContext1, $entityContext2], new ParameterBag())
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrorsForSubresource - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrorsForSubresource - entity 2',
                'flushData',
                'post_flush_data - entity 1',
                'collectFormErrorsForSubresource - entity 1',
                'post_flush_data - entity 2',
                'collectFormErrorsForSubresource - entity 2',
                'commitTransaction',
                'post_save_data - entity 1',
                'collectFormErrorsForSubresource - entity 1',
                'post_save_data - entity 2',
                'collectFormErrorsForSubresource - entity 2'
            ],
            $calls
        );
    }

    public function testFlushDataWhenFlushFailed(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $exception = new \Exception('some error');

        $calls = [];
        $this->expectsFlush($calls, $em, $exception);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName) use (&$calls) {
                $calls[] = $eventName;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'collectFormErrors';
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->flushDataWithException($em, [$this->getFormContext($this->getForm())], $exception);

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data',
                'collectFormErrors',
                'flushData',
                'rollbackTransaction'
            ],
            $calls
        );
    }

    public function testFlushDataWhenFlushFailedAndThenRollbackFailedAsWell(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2);

        $em = $this->createMock(EntityManagerInterface::class);
        $exception = new \Exception('some error');
        $rollbackException = new \Exception('some rollback error');

        $calls = [];
        $connection = $this->createMock(Connection::class);

        $em->expects(self::any())
            ->method('getConnection')
            ->willReturnCallback(function () use (&$calls, $connection) {
                $calls[] = 'getConnection';

                return $connection;
            });

        $connection->expects(self::any())
            ->method('beginTransaction')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'beginTransaction';
            });
        $em->expects(self::any())
            ->method('flush')
            ->willReturnCallback(function () use (&$calls, $exception) {
                $calls[] = 'flushData';

                if (null !== $exception) {
                    throw $exception;
                }
            });
        $connection->expects(self::any())
            ->method('commit')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'commitTransaction';
            });
        $connection->expects(self::any())
            ->method('rollBack')
            ->willReturnCallback(function () use (&$calls, $rollbackException) {
                $calls[] = 'rollbackTransaction';
                throw $rollbackException;
            });

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName) use (&$calls) {
                $calls[] = $eventName;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'collectFormErrors';
            });

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'The database rollback operation failed in API flush data handler.',
                ['exception' => $rollbackException, 'entityClasses' => \stdClass::class]
            );

        $this->flushDataWithException($em, [$entityContext1, $entityContext2], $exception);

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data',
                'collectFormErrors',
                'pre_flush_data',
                'collectFormErrors',
                'flushData',
                'rollbackTransaction'
            ],
            $calls
        );
    }

    public function testFlushDataWhenPreFlushDataFailed(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2);

        $em = $this->createMock(EntityManagerInterface::class);
        $exception = new \Exception('some error');

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (
                string $eventName,
                FormInterface $form
            ) use (
                &$calls,
                $form1,
                $form2,
                $exception
            ) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;

                if ($form1 === $form && CustomizeFormDataContext::EVENT_PRE_FLUSH_DATA === $eventName) {
                    throw $exception;
                }
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->flushDataWithException($em, [$entityContext1, $entityContext2], $exception);

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'rollbackTransaction'
            ],
            $calls
        );
    }

    public function testFlushDataWhenPostFlushDataFailed(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2);

        $em = $this->createMock(EntityManagerInterface::class);
        $exception = new \Exception('some error');

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (
                string $eventName,
                FormInterface $form
            ) use (
                &$calls,
                $form1,
                $form2,
                $exception
            ) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;

                if ($form1 === $form && CustomizeFormDataContext::EVENT_POST_FLUSH_DATA === $eventName) {
                    throw $exception;
                }
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->flushDataWithException($em, [$entityContext1, $entityContext2], $exception);

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'flushData',
                'post_flush_data - entity 1',
                'rollbackTransaction'
            ],
            $calls
        );
    }

    public function testFlushDataWhenPostSaveDataFailed(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2);

        $em = $this->createMock(EntityManagerInterface::class);
        $exception = new \Exception('some error');

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (
                string $eventName,
                FormInterface $form
            ) use (
                &$calls,
                $form1,
                $form2,
                $exception
            ) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;

                if ($form1 === $form && CustomizeFormDataContext::EVENT_POST_SAVE_DATA === $eventName) {
                    throw $exception;
                }
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->flushDataWithException($em, [$entityContext1, $entityContext2], $exception);

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'flushData',
                'post_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'post_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'commitTransaction',
                'post_save_data - entity 1'
            ],
            $calls
        );
    }

    public function testFlushDataWhenFormErrorsAddedInPreFlushDataHandlers(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1, null, true);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2, null, true);

        $entityContext1->expects(self::once())
            ->method('hasErrors')
            ->willReturnOnConsecutiveCalls(true);
        $entityContext2->expects(self::never())
            ->method('hasErrors');

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $form1, $form2) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext([$entityContext1, $entityContext2], new ParameterBag())
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'rollbackTransaction'
            ],
            $calls
        );
    }

    public function testFlushDataWhenFormErrorsAddedInPostFlushDataHandlers(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1, null, true);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2, null, true);

        $entityContext1->expects(self::exactly(2))
            ->method('hasErrors')
            ->willReturnOnConsecutiveCalls(false, true);
        $entityContext2->expects(self::once())
            ->method('hasErrors')
            ->willReturnOnConsecutiveCalls(false);

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $form1, $form2) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext([$entityContext1, $entityContext2], new ParameterBag())
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'flushData',
                'post_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'post_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'rollbackTransaction'
            ],
            $calls
        );
    }

    public function testFlushDataWhenFormErrorsAddedInPostSaveDataHandlers(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1, null, true);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2, null, true);

        $entityContext1->expects(self::exactly(2))
            ->method('hasErrors')
            ->willReturnOnConsecutiveCalls(false, false);
        $entityContext2->expects(self::exactly(2))
            ->method('hasErrors')
            ->willReturnOnConsecutiveCalls(false, false);

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $form1, $form2) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext([$entityContext1, $entityContext2], new ParameterBag())
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'flushData',
                'post_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'post_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'commitTransaction',
                'post_save_data - entity 1',
                'collectFormErrors - entity 1',
                'post_save_data - entity 2',
                'collectFormErrors - entity 2'
            ],
            $calls
        );
    }

    public function testFlushDataWithIncludedEntities(): void
    {
        $primaryEntityForm = $this->getForm();

        $includedEntityForm = $this->getForm();
        $includedEntityData = $this->createMock(IncludedEntityData::class);
        $includedEntityData->expects(self::any())
            ->method('getForm')
            ->willReturn($includedEntityForm);
        $itemIncludedEntities = new IncludedEntityCollection();
        $itemIncludedEntities->add(
            $this->createMock(\stdClass::class),
            \stdClass::class,
            'incl_id',
            $includedEntityData
        );

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (
                string $eventName,
                FormInterface $form
            ) use (
                &$calls,
                $primaryEntityForm,
                $includedEntityForm
            ) {
                $description = 'unknown entity';
                if ($primaryEntityForm === $form) {
                    $description = 'primary entity';
                } elseif ($includedEntityForm === $form) {
                    $description = 'included entity';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext(
                [$this->getFormContext($primaryEntityForm, $itemIncludedEntities)],
                new ParameterBag()
            )
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - included entity',
                'pre_flush_data - primary entity',
                'flushData',
                'post_flush_data - included entity',
                'post_flush_data - primary entity',
                'commitTransaction',
                'post_save_data - included entity',
                'post_save_data - primary entity'
            ],
            $calls
        );
    }

    public function testFlushDataWhenNoIncludedEntityForm(): void
    {
        $primaryEntityForm = $this->getForm();

        $includedEntityData = $this->createMock(IncludedEntityData::class);
        $includedEntityData->expects(self::any())
            ->method('getForm')
            ->willReturn(null);
        $itemIncludedEntities = new IncludedEntityCollection();
        $itemIncludedEntities->add(
            $this->createMock(\stdClass::class),
            \stdClass::class,
            'incl_id',
            $includedEntityData
        );

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $primaryEntityForm) {
                $description = 'unknown entity';
                if ($primaryEntityForm === $form) {
                    $description = 'primary entity';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'collectFormErrors';
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext(
                [$this->getFormContext($primaryEntityForm, $itemIncludedEntities)],
                new ParameterBag()
            )
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - primary entity',
                'collectFormErrors',
                'flushData',
                'post_flush_data - primary entity',
                'collectFormErrors',
                'commitTransaction',
                'post_save_data - primary entity',
                'collectFormErrors'
            ],
            $calls
        );
    }

    public function testFlushDataWhenNoPrimaryEntityForm(): void
    {
        $includedEntityForm = $this->getForm();
        $includedEntityData = $this->createMock(IncludedEntityData::class);
        $includedEntityData->expects(self::any())
            ->method('getForm')
            ->willReturn($includedEntityForm);
        $itemIncludedEntities = new IncludedEntityCollection();
        $itemIncludedEntities->add(
            $this->createMock(\stdClass::class),
            \stdClass::class,
            'incl_id',
            $includedEntityData
        );

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $includedEntityForm) {
                $description = 'unknown entity';
                if ($includedEntityForm === $form) {
                    $description = 'included entity';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'collectFormErrors';
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext(
                [$this->getFormContext(null, $itemIncludedEntities)],
                new ParameterBag()
            )
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - included entity',
                'collectFormErrors',
                'flushData',
                'post_flush_data - included entity',
                'collectFormErrors',
                'commitTransaction',
                'post_save_data - included entity',
                'collectFormErrors'
            ],
            $calls
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testFlushDataWhenAdditionalEntitiesWereAddedOnPreFlushEvent(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2);

        $em = $this->createMock(EntityManagerInterface::class);

        $additionalEntity1 = new \stdClass();
        $additionalEntity2 = new \stdClass();
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $em->expects(self::any())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);
        $metadataFactory->expects(self::any())
            ->method('isTransient')
            ->willReturn(false);
        $uow = $this->createMock(UnitOfWork::class);
        $em->expects(self::any())
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $uow->expects(self::any())
            ->method('getEntityState')
            ->willReturnCallback(function ($entity) use ($additionalEntity1, $additionalEntity2) {
                if ($entity === $additionalEntity1) {
                    return UnitOfWork::STATE_NEW;
                }
                if ($entity === $additionalEntity2) {
                    return UnitOfWork::STATE_MANAGED;
                }
                throw new \LogicException('Unexpected entity');
            });

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (
                string $eventName,
                FormInterface $form
            ) use (
                &$calls,
                $form1,
                $form2,
                $entityContext1,
                $additionalEntity1,
                $additionalEntity2
            ) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;

                if ($form1 === $form && CustomizeFormDataContext::EVENT_PRE_FLUSH_DATA === $eventName) {
                    $entityContext1->addAdditionalEntity($additionalEntity1);
                    $entityContext1->addAdditionalEntityToRemove($additionalEntity2);
                }
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext([$entityContext1, $entityContext2], new ParameterBag())
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'persist entity',
                'remove entity',
                'flushData',
                'post_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'post_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'commitTransaction',
                'post_save_data - entity 1',
                'collectFormErrors - entity 1',
                'post_save_data - entity 2',
                'collectFormErrors - entity 2'
            ],
            $calls
        );
    }

    public function testFlushDataWhenBatchOperation(): void
    {
        $form1 = $this->getForm();
        $entityContext1 = $this->getFormContext($form1);
        $form2 = $this->getForm();
        $entityContext2 = $this->getFormContext($form2);

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $form1, $form2) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                } elseif ($form2 === $form) {
                    $description = 'entity 2';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1, $entityContext2) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                } elseif ($entityContext2 === $formContext) {
                    $description = 'entity 2';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext([$entityContext1, $entityContext2], new ParameterBag(), true)
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'pre_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'flushData',
                'post_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'post_flush_data - entity 2',
                'collectFormErrors - entity 2',
                'commitTransaction',
                'post_save_data - entity 1',
                'collectFormErrors - entity 1',
                'post_save_data - entity 2',
                'collectFormErrors - entity 2'
            ],
            $calls
        );
    }

    public function testFlushDataWhenValidateFlag(): void
    {
        $form1 = $this->getForm();

        $entityContext1 = $this->getFormContext($form1);
        $entityContext1->expects(self::once())
            ->method('get')
            ->with(SetOperationFlags::VALIDATE_FLAG)
            ->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);

        $calls = [];
        $this->expectsFlush($calls, $em);

        $this->customizeFormDataEventDispatcher->expects(self::any())
            ->method('dispatch')
            ->willReturnCallback(function (string $eventName, FormInterface $form) use (&$calls, $form1) {
                $description = 'unknown entity';
                if ($form1 === $form) {
                    $description = 'entity 1';
                }
                $calls[] = $eventName . ' - ' . $description;
            });

        $this->formErrorsCollector->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (FormContext $formContext) use (&$calls, $entityContext1) {
                $description = 'unknown entity';
                if ($entityContext1 === $formContext) {
                    $description = 'entity 1';
                }
                $calls[] = 'collectFormErrors - ' . $description;
            });

        $this->logger->expects(self::never())
            ->method(self::anything());

        $this->handler->flushData(
            $em,
            new FlushDataHandlerContext([$entityContext1], new ParameterBag())
        );

        self::assertEquals(
            [
                'getConnection',
                'beginTransaction',
                'pre_flush_data - entity 1',
                'collectFormErrors - entity 1',
                'flushData',
                'rollback_validated_request - entity 1',
                'collectFormErrors - entity 1',
                'rollbackTransaction'
            ],
            $calls
        );
    }
}
