<?php

namespace Oro\Bundle\ReminderBundle\Tests\Unit\Entity\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ReminderBundle\Entity\Collection\RemindersPersistentCollection;
use Oro\Bundle\ReminderBundle\Entity\Manager\ReminderManager;
use Oro\Bundle\ReminderBundle\Entity\RemindableInterface;
use Oro\Bundle\ReminderBundle\Entity\Reminder;
use Oro\Bundle\ReminderBundle\Entity\Repository\ReminderRepository;
use Oro\Bundle\ReminderBundle\Model\ReminderDataInterface;
use Oro\Bundle\ReminderBundle\Model\ReminderInterval;
use Oro\Bundle\ReminderBundle\Tests\Unit\Fixtures\RemindableEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReminderManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DoctrineHelper&MockObject $doctrineHelper;
    private ReminderManager $manager;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);

        $this->doctrineHelper->expects(self::any())
            ->method('getEntityManager')
            ->willReturn($this->entityManager);

        $this->manager = new ReminderManager($this->doctrineHelper);
    }

    public function testSaveRemindersOnCreate(): void
    {
        $fooReminder = $this->createReminder(100);
        $barReminder = $this->createReminder(200);
        $reminders = new ArrayCollection([$fooReminder, $barReminder]);

        $reminderData = $this->createMock(ReminderDataInterface::class);

        $entityId = 101;
        $entity = $this->createMock(RemindableInterface::class);
        $entityClass = \get_class($entity);

        $entity->expects(self::once())
            ->method('getReminders')
            ->willReturn($reminders);
        $entity->expects(self::once())
            ->method('getReminderData')
            ->willReturn($reminderData);

        $this->expectReminderSync($fooReminder, $entityClass, $entityId, $reminderData);
        $this->expectReminderSync($barReminder, $entityClass, $entityId, $reminderData);

        $this->doctrineHelper->expects(self::once())
            ->method('getSingleEntityIdentifier')
            ->willReturn($entityId);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityClass')
            ->willReturn($entityClass);

        $this->entityManager->expects(self::exactly(2))
            ->method('persist')
            ->withConsecutive(
                [self::identicalTo($fooReminder)],
                [self::identicalTo($barReminder)]
            );

        $this->manager->saveReminders($entity);
    }

    public function testSaveEmptyEntityId(): void
    {
        $entity = $this->createMock(RemindableInterface::class);

        $this->doctrineHelper->expects(self::once())
            ->method('getSingleEntityIdentifier')
            ->willReturn(null);

        $this->entityManager->expects(self::never())
            ->method('persist');

        $this->manager->saveReminders($entity);
    }

    public function testSaveRemindersOnUpdate(): void
    {
        $fooReminder = $this->createReminder(100);
        $barReminder = $this->createReminder(200);
        $remindersCollection = $this->createMock(RemindersPersistentCollection::class);

        $remindersCollection->expects(self::once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$fooReminder]));

        $remindersCollection->expects(self::once())
            ->method('isDirty')
            ->willReturn(true);
        $remindersCollection->expects(self::once())
            ->method('getInsertDiff')
            ->willReturn([$fooReminder]);
        $remindersCollection->expects(self::once())
            ->method('getDeleteDiff')
            ->willReturn([$barReminder]);

        $reminderData = $this->createMock(ReminderDataInterface::class);

        $entityId = 101;
        $entity = $this->createMock(RemindableInterface::class);
        $entityClass = get_class($entity);

        $entity->expects(self::once())
            ->method('getReminders')
            ->willReturn($remindersCollection);
        $entity->expects(self::once())
            ->method('getReminderData')
            ->willReturn($reminderData);

        $this->expectReminderSync($fooReminder, $entityClass, $entityId, $reminderData);

        $this->doctrineHelper->expects(self::once())
            ->method('getSingleEntityIdentifier')
            ->willReturn($entityId);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityClass')
            ->willReturn($entityClass);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::identicalTo($fooReminder));

        $this->manager->saveReminders($entity);
    }

    /**
     * @dataProvider emptyRemindersProvider
     */
    public function testSaveRemindersEmpty(Collection|array $reminders): void
    {
        $entityId = 101;
        $entity = $this->createMock(RemindableInterface::class);
        $entityClass = get_class($entity);

        $entity->expects(self::once())
            ->method('getReminders')
            ->willReturn($reminders);
        $entity->expects(self::never())
            ->method('getReminderData');

        $this->doctrineHelper->expects(self::once())
            ->method('getSingleEntityIdentifier')
            ->willReturn($entityId);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityClass')
            ->willReturn($entityClass);

        $this->manager->saveReminders($entity);
    }

    public function emptyRemindersProvider(): array
    {
        $remindersPersistentCollection = $this->createMock(RemindersPersistentCollection::class);
        $remindersPersistentCollection->expects(self::any())
            ->method('isEmpty')
            ->willReturn(true);

        return [
            [$remindersPersistentCollection],
            [new ArrayCollection()],
            [[]],
        ];
    }

    public function testLoadReminders(): void
    {
        $entityId = 101;
        $entity = $this->createMock(RemindableInterface::class);
        $entityClass = get_class($entity);

        $repository = $this->createMock(ReminderRepository::class);

        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(Reminder::class)
            ->willReturn($repository);

        $this->doctrineHelper->expects(self::once())
            ->method('getSingleEntityIdentifier')
            ->willReturn($entityId);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityClass')
            ->willReturn($entityClass);

        $entity->expects(self::once())
            ->method('setReminders')
            ->willReturnCallback(function ($reminders) {
                self::assertInstanceOf(RemindersPersistentCollection::class, $reminders);

                return true;
            });

        $this->manager->loadReminders($entity);
    }

    public function testApplyRemindersNoItems(): void
    {
        $entityClassName = Reminder::class;
        $items = [];

        $this->entityManager->expects(self::never())
            ->method('getRepository');

        $this->manager->applyReminders($items, $entityClassName);
    }

    public function testApplyRemindersNotRemindableEntity(): void
    {
        $entityClassName = Reminder::class;
        $items = [
            ['id' => 1, 'subject' => 'item1'],
            ['id' => 2, 'subject' => 'item2']
        ];

        $this->entityManager->expects(self::never())
            ->method('getRepository');

        $this->manager->applyReminders($items, $entityClassName);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testApplyReminders(): void
    {
        $entityClassName = RemindableEntity::class;
        $items = [
            ['id' => 1, 'subject' => 'item1'],
            ['id' => 2, 'subject' => 'item2'],
            ['id' => 3, 'subject' => 'item3']
        ];

        $query = $this->createMock(AbstractQuery::class);
        $qb = $this->createMock(QueryBuilder::class);
        $reminderRepo = $this->createMock(ReminderRepository::class);
        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(Reminder::class)
            ->willReturn($reminderRepo);
        $reminderRepo->expects(self::once())
            ->method('findRemindersByEntitiesQueryBuilder')
            ->with($entityClassName, [1, 2, 3])
            ->willReturn($qb);
        $qb->expects(self::once())
            ->method('select')
            ->with('reminder.relatedEntityId, reminder.method, reminder.intervalNumber, reminder.intervalUnit')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);
        $query->expects(self::once())
            ->method('getArrayResult')
            ->willReturn(
                [
                    [
                        'relatedEntityId' => 3,
                        'method'          => 'email',
                        'intervalNumber'  => 1,
                        'intervalUnit'    => ReminderInterval::UNIT_HOUR
                    ],
                    [
                        'relatedEntityId' => 2,
                        'method'          => 'email',
                        'intervalNumber'  => 15,
                        'intervalUnit'    => ReminderInterval::UNIT_MINUTE
                    ],
                    [
                        'relatedEntityId' => 2,
                        'method'          => 'flash',
                        'intervalNumber'  => 10,
                        'intervalUnit'    => ReminderInterval::UNIT_MINUTE
                    ],
                ]
            );

        $this->manager->applyReminders($items, $entityClassName);
        self::assertEquals(
            [
                [
                    'id'      => 1,
                    'subject' => 'item1',
                ],
                [
                    'id'        => 2,
                    'subject'   => 'item2',
                    'reminders' => [
                        [
                            'method'   => 'email',
                            'interval' => [
                                'number' => 15,
                                'unit'   => ReminderInterval::UNIT_MINUTE
                            ]
                        ],
                        [
                            'method'   => 'flash',
                            'interval' => [
                                'number' => 10,
                                'unit'   => ReminderInterval::UNIT_MINUTE
                            ]
                        ],
                    ]
                ],
                [
                    'id'        => 3,
                    'subject'   => 'item3',
                    'reminders' => [
                        [
                            'method'   => 'email',
                            'interval' => [
                                'number' => 1,
                                'unit'   => ReminderInterval::UNIT_HOUR
                            ]
                        ],
                    ]
                ],
            ],
            $items
        );
    }

    private function expectReminderSync(
        MockObject $reminder,
        string $entityClassName,
        int $entityId,
        ReminderDataInterface $reminderData
    ): void {
        $reminder->expects(self::once())
            ->method('setRelatedEntityClassName')
            ->with($entityClassName);
        $reminder->expects(self::once())
            ->method('setRelatedEntityId')
            ->with($entityId);
        $reminder->expects(self::once())
            ->method('setReminderData')
            ->with($reminderData);
    }

    private function createReminder(int $id): Reminder&MockObject
    {
        $result = $this->createMock(Reminder::class);
        $result->expects(self::any())
            ->method('getId')
            ->willReturn($id);

        return $result;
    }
}
