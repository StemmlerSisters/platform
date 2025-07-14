<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Form\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityConfigBundle\Config\ConfigHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Form\Handler\RemoveRestoreConfigFieldHandler;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Validator\FieldNameValidationHelper;
use Oro\Bundle\TestFrameworkBundle\Entity\TestActivityTarget;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class RemoveRestoreConfigFieldHandlerTest extends TestCase
{
    private const SAMPLE_ERROR_MESSAGE = 'Restore error message';
    private const SAMPLE_SUCCESS_MESSAGE = 'Entity config was successfully saved';
    private const SAMPLE_VALIDATION_ERROR_MESSAGE1 = 'Validation error 1';
    private const SAMPLE_VALIDATION_ERROR_MESSAGE2 = 'Validation error 2';

    private ConfigManager&MockObject $configManager;
    private FieldNameValidationHelper&MockObject $validationHelper;
    private ConfigHelper&MockObject $configHelper;
    private Session&MockObject $session;
    private RequestStack&MockObject $requestStack;
    private FieldConfigModel&MockObject $fieldConfigModel;
    private RemoveRestoreConfigFieldHandler $handler;
    private ManagerRegistry&MockObject $registry;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->validationHelper = $this->createMock(FieldNameValidationHelper::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->session = $this->createMock(Session::class);
        $this->fieldConfigModel = $this->createMock(FieldConfigModel::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->expects($this->any())
            ->method('getSession')
            ->willReturn($this->session);

        $this->handler = new RemoveRestoreConfigFieldHandler(
            $this->configManager,
            $this->validationHelper,
            $this->configHelper,
            $this->requestStack,
            $this->registry
        );
    }

    private function expectsJsonResponseWithContent(JsonResponse $response, array $expectedContent): void
    {
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(json_encode($expectedContent), $response->getContent());
        $this->assertEquals(JsonResponse::HTTP_OK, $response->getStatusCode());
    }

    private function expectsConfigManagerPersistAndFlush(ConfigInterface $fieldConfig, ConfigInterface $entityConfig)
    {
        $this->configManager->expects($this->exactly(2))
            ->method('persist')
            ->withConsecutive([$fieldConfig], [$entityConfig]);

        $this->configManager->expects($this->once())
            ->method('flush');
    }

    public function testHandleRemove(): void
    {
        $this->validationHelper->expects($this->once())
            ->method('getRemoveFieldValidationErrors')
            ->with($this->fieldConfigModel)
            ->willReturn([]);

        $entityConfig = $this->prepareEntityConfig();
        $fieldConfig = $this->prepareFieldConfig();
        $fieldConfig->expects($this->once())
            ->method('set')
            ->with('state', ExtendScope::STATE_DELETE);

        $this->configManager->expects($this->exactly(2))
            ->method('persist')
            ->withConsecutive([$fieldConfig], [$entityConfig]);

        $this->configManager->expects($this->once())
            ->method('flush');

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects($this->once())
            ->method('add')
            ->with('success', self::SAMPLE_SUCCESS_MESSAGE);

        $this->session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $expectedContent = [
            'message' => self::SAMPLE_SUCCESS_MESSAGE,
            'successful' => true
        ];

        $response = $this->handler->handleRemove($this->fieldConfigModel, self::SAMPLE_SUCCESS_MESSAGE);

        $this->expectsJsonResponseWithContent($response, $expectedContent);
    }

    public function testHandleRemoveValidationError(): void
    {
        $this->validationHelper->expects($this->once())
            ->method('getRemoveFieldValidationErrors')
            ->with($this->fieldConfigModel)
            ->willReturn([
                self::SAMPLE_VALIDATION_ERROR_MESSAGE1,
                self::SAMPLE_VALIDATION_ERROR_MESSAGE2
            ]);

        $fieldConfig = $this->createMock(ConfigInterface::class);
        $fieldConfig->expects($this->never())
            ->method('set')
            ->with('state', ExtendScope::STATE_DELETE);

        $this->configManager->expects($this->never())
            ->method('flush');

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects($this->exactly(2))
            ->method('add')
            ->withConsecutive(
                ['error', self::SAMPLE_VALIDATION_ERROR_MESSAGE1],
                ['error', self::SAMPLE_VALIDATION_ERROR_MESSAGE2]
            );

        $this->session->expects($this->exactly(2))
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $expectedContent = [
            'message' => sprintf(
                '%s. %s',
                self::SAMPLE_VALIDATION_ERROR_MESSAGE1,
                self::SAMPLE_VALIDATION_ERROR_MESSAGE2
            ),
            'successful' => false
        ];

        $response = $this->handler->handleRemove($this->fieldConfigModel, self::SAMPLE_SUCCESS_MESSAGE);

        $this->expectsJsonResponseWithContent($response, $expectedContent);
    }

    public function testHandleRestoreWhenFieldCannotBeRestored(): void
    {
        $this->validationHelper->expects($this->once())
            ->method('canFieldBeRestored')
            ->with($this->fieldConfigModel)
            ->willReturn(false);

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects($this->once())
            ->method('add')
            ->with('error', self::SAMPLE_ERROR_MESSAGE);

        $this->session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $this->configManager->expects($this->never())
            ->method('persist');

        $this->configManager->expects($this->never())
            ->method('flush');

        $expectedContent = [
            'message' => self::SAMPLE_ERROR_MESSAGE,
            'successful' => false
        ];

        $response = $this->handler->handleRestore(
            $this->fieldConfigModel,
            self::SAMPLE_ERROR_MESSAGE,
            self::SAMPLE_SUCCESS_MESSAGE
        );

        $this->expectsJsonResponseWithContent($response, $expectedContent);
    }

    public function testHandleRestoreWhenFieldCanBeRestoredAndEntityClassNotExists(): void
    {
        $entityClassName = 'ClassNotExists';
        $expectedState = ExtendScope::STATE_NEW;

        $this->prepareConfigMocksForRestoreCalls($entityClassName, $expectedState);

        $expectedContent = [
            'message' => self::SAMPLE_SUCCESS_MESSAGE,
            'successful' => true
        ];

        $response = $this->handler->handleRestore(
            $this->fieldConfigModel,
            self::SAMPLE_ERROR_MESSAGE,
            self::SAMPLE_SUCCESS_MESSAGE
        );

        $this->expectsJsonResponseWithContent($response, $expectedContent);
    }

    public function testHandleRestoreWhenFieldCanBeRestoredAndEntityClassNotManaged(): void
    {
        $entityClassName = \stdClass::class;
        $expectedState = ExtendScope::STATE_NEW;

        $this->prepareConfigMocksForRestoreCalls($entityClassName, $expectedState);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($entityClassName)
            ->willReturn(null);

        $expectedContent = [
            'message' => self::SAMPLE_SUCCESS_MESSAGE,
            'successful' => true
        ];

        $response = $this->handler->handleRestore(
            $this->fieldConfigModel,
            self::SAMPLE_ERROR_MESSAGE,
            self::SAMPLE_SUCCESS_MESSAGE
        );

        $this->expectsJsonResponseWithContent($response, $expectedContent);
    }

    public function testHandleRestoreWhenFieldCanBeRestoredAndFieldPropertyNotExists(): void
    {
        $entityClassName = TestActivityTarget::class;
        $expectedState = ExtendScope::STATE_NEW;

        $this->prepareConfigMocksForRestoreCalls($entityClassName, $expectedState);

        $this->fieldConfigModel->expects($this->any())
            ->method('getFieldName')
            ->willReturn('NotExistentProperty');

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->any())
            ->method('hasField')
            ->with('NotExistentProperty')
            ->willReturn(false);
        $metadata->expects($this->any())
            ->method('hasAssociation')
            ->with('NotExistentProperty')
            ->willReturn(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getClassMetadata')
            ->with($entityClassName)
            ->willReturn($metadata);
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($entityClassName)
            ->willReturn($em);

        $expectedContent = [
            'message' => self::SAMPLE_SUCCESS_MESSAGE,
            'successful' => true
        ];

        $response = $this->handler->handleRestore(
            $this->fieldConfigModel,
            self::SAMPLE_ERROR_MESSAGE,
            self::SAMPLE_SUCCESS_MESSAGE
        );

        $this->expectsJsonResponseWithContent($response, $expectedContent);
    }

    public function testHandleRestoreWhenFieldCanBeRestoredAndClassNameAndFieldExist(): void
    {
        $entityClassName = TestActivityTarget::class;
        $expectedState = ExtendScope::STATE_RESTORE;
        $fieldName = 'id';

        $this->prepareConfigMocksForRestoreCalls($entityClassName, $expectedState);

        $this->fieldConfigModel->expects($this->once())
            ->method('getFieldName')
            ->willReturn($fieldName);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->any())
            ->method('hasField')
            ->with($fieldName)
            ->willReturn(true);
        $metadata->expects($this->any())
            ->method('hasAssociation')
            ->with($fieldName)
            ->willReturn(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getClassMetadata')
            ->with($entityClassName)
            ->willReturn($metadata);
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($entityClassName)
            ->willReturn($em);

        $expectedContent = [
            'message' => self::SAMPLE_SUCCESS_MESSAGE,
            'successful' => true
        ];

        $response = $this->handler->handleRestore(
            $this->fieldConfigModel,
            self::SAMPLE_ERROR_MESSAGE,
            self::SAMPLE_SUCCESS_MESSAGE
        );

        $this->expectsJsonResponseWithContent($response, $expectedContent);
    }

    private function prepareEntityConfigModel(string $entityClassName): void
    {
        $entity = $this->createMock(EntityConfigModel::class);
        $entity->expects($this->any())
            ->method('getClassName')
            ->willReturn($entityClassName);

        $this->fieldConfigModel->expects($this->any())
            ->method('getEntity')
            ->willReturn($entity);
    }

    private function prepareEntityConfig(): ConfigInterface
    {
        $entityConfig = $this->createMock(ConfigInterface::class);
        $entityConfig->expects($this->once())
            ->method('set')
            ->with('upgradeable', true);

        $this->configHelper->expects($this->once())
            ->method('getEntityConfigByField')
            ->with($this->fieldConfigModel, 'extend')
            ->willReturn($entityConfig);

        return $entityConfig;
    }

    private function prepareFieldConfig(): ConfigInterface&MockObject
    {
        $fieldConfig = $this->createMock(ConfigInterface::class);
        $this->configHelper->expects($this->once())
            ->method('getFieldConfig')
            ->with($this->fieldConfigModel, 'extend')
            ->willReturn($fieldConfig);

        return $fieldConfig;
    }

    private function prepareConfigMocksForRestoreCalls(string $entityClassName, string $expectedState): void
    {
        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects($this->once())
            ->method('add')
            ->with('success', self::SAMPLE_SUCCESS_MESSAGE);

        $this->session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $this->validationHelper->expects($this->once())
            ->method('canFieldBeRestored')
            ->with($this->fieldConfigModel)
            ->willReturn(true);

        $this->prepareEntityConfigModel($entityClassName);
        $entityConfig = $this->prepareEntityConfig();
        $fieldConfig = $this->prepareFieldConfig();
        $fieldConfig->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                ['state'],
                ['is_deleted', false]
            );

        $this->expectsConfigManagerPersistAndFlush($fieldConfig, $entityConfig);
    }
}
