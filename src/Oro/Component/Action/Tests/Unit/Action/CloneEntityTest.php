<?php

namespace Oro\Component\Action\Tests\Unit\Action;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Oro\Component\Action\Action\CloneEntity;
use Oro\Component\Action\Exception\NotManageableEntityException;
use Oro\Component\ConfigExpression\ContextAccessor;
use Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ItemStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Contracts\Translation\TranslatorInterface;

class CloneEntityTest extends TestCase
{
    private ManagerRegistry&MockObject $registry;
    private TranslatorInterface&MockObject $translator;
    private FlashBagInterface&MockObject $flashBag;
    private LoggerInterface&MockObject $logger;
    private CloneEntity $action;

    #[\Override]
    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);
        $session = $this->createMock(Session::class);
        $session->expects(self::any())
            ->method('getFlashBag')
            ->willReturn($this->flashBag);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects(self::any())
            ->method('getSession')
            ->willReturn($session);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new CloneEntity(
            new ContextAccessor(),
            $this->registry,
            $this->translator,
            $requestStack,
            $this->logger
        );
        $this->action->setDispatcher($this->createMock(EventDispatcher::class));
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $options): void
    {
        $meta = $this->createMock(ClassMetadata::class);
        $meta->expects($this->any())
            ->method('getIdentifierValues')
            ->willReturn(['id' => 4]);
        $meta->expects($this->once())
            ->method('setIdentifierValues');

        $em = $this->createMock(ObjectManager::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ClassUtils::getClass($options[CloneEntity::OPTION_KEY_TARGET])));
        $em->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($meta);

        if (isset($options[CloneEntity::OPTION_KEY_FLUSH]) && false === $options[CloneEntity::OPTION_KEY_FLUSH]) {
            $em->expects($this->never())
                ->method('flush');
        } else {
            $em->expects($this->once())
                ->method('flush')
                ->with($this->isInstanceOf(ClassUtils::getClass($options[CloneEntity::OPTION_KEY_TARGET])));
        }

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(ClassUtils::getClass($options[CloneEntity::OPTION_KEY_TARGET]))
            ->willReturn($em);

        $context = new ItemStub([]);
        $attributeName = (string)$options[CloneEntity::OPTION_KEY_ATTRIBUTE];
        $this->action->initialize($options);
        $this->action->execute($context);
        $this->assertNotNull($context->{$attributeName});
        $this->assertInstanceOf(
            ClassUtils::getClass($options[CloneEntity::OPTION_KEY_TARGET]),
            $context->{$attributeName}
        );

        /** @var ItemStub $entity */
        $entity = $context->{$attributeName};
        $expectedData = !empty($options[CloneEntity::OPTION_KEY_DATA]) ?
            $options[CloneEntity::OPTION_KEY_DATA] :
            [];
        $this->assertEquals($expectedData, $entity->getData());
    }

    public function executeDataProvider(): array
    {
        $stubTarget = new ItemStub();

        return [
            'without data' => [
                'options' => [
                    CloneEntity::OPTION_KEY_TARGET    => $stubTarget,
                    CloneEntity::OPTION_KEY_ATTRIBUTE => new PropertyPath('test_attribute'),
                ]
            ],
            'with data' => [
                'options' => [
                    CloneEntity::OPTION_KEY_TARGET     => $stubTarget,
                    CloneEntity::OPTION_KEY_ATTRIBUTE => new PropertyPath('test_attribute'),
                    CloneEntity::OPTION_KEY_DATA      => ['key1' => 'value1', 'key2' => 'value2'],
                ]
            ],
            'without flush' => [
                'options' => [
                    CloneEntity::OPTION_KEY_TARGET     => $stubTarget,
                    CloneEntity::OPTION_KEY_ATTRIBUTE => new PropertyPath('test_attribute'),
                    CloneEntity::OPTION_KEY_DATA      => ['key1' => 'value1', 'key2' => 'value2'],
                    CloneEntity::OPTION_KEY_FLUSH     => false
                ]
            ]
        ];
    }

    public function testExecuteEntityNotManageable(): void
    {
        $this->expectException(NotManageableEntityException::class);
        $this->expectExceptionMessage(sprintf('Entity class "%s" is not manageable.', \stdClass::class));

        $options = [
            CloneEntity::OPTION_KEY_TARGET     => new \stdClass(),
            CloneEntity::OPTION_KEY_ATTRIBUTE => $this->createMock(PropertyPath::class)
        ];
        $context = [];
        $this->action->initialize($options);
        $this->action->execute($context);
    }

    public function testExecuteCantCreateEntity(): void
    {
        $meta = $this->createMock(ClassMetadata::class);
        $meta->expects($this->any())
            ->method('getIdentifierValues')
            ->willReturn(['id' => 5]);
        $meta->expects($this->once())
            ->method('setIdentifierValues');

        $em = $this->createMock(ObjectManager::class);
        $em->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($meta);
        $em->expects($this->once())
            ->method('persist')
            ->willThrowException(new \Exception('Test exception.'));

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($em);

        $this->flashBag->expects($this->once())
            ->method('add');
        $this->logger->expects($this->once())
            ->method('error');

        $options = [
            CloneEntity::OPTION_KEY_TARGET    => new \stdClass(),
            CloneEntity::OPTION_KEY_ATTRIBUTE => new PropertyPath('[test_attribute]')
        ];
        $context = [];
        $this->action->initialize($options);
        $this->action->execute($context);
    }
}
