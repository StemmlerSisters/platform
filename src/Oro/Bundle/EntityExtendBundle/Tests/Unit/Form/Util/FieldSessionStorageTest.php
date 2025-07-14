<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Form\Util;

use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityExtendBundle\Form\Util\FieldSessionStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

class FieldSessionStorageTest extends TestCase
{
    private const MODEL_ID = 42;
    private const FIELD_NAME = 'someFieldName';
    private const FIELD_TYPE = 'enum';

    private Session&MockObject $session;
    private RequestStack&MockObject $requestStack;
    private FieldSessionStorage $storage;

    #[\Override]
    protected function setUp(): void
    {
        $this->session = $this->createMock(Session::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->expects($this->any())
            ->method('getSession')
            ->willReturn($this->session);

        $this->storage = new FieldSessionStorage($this->requestStack);
    }

    public function testGetFieldInfo(): void
    {
        $entityConfigModel = $this->createMock(EntityConfigModel::class);
        $entityConfigModel->expects($this->exactly(2))
            ->method('getId')
            ->willReturn(self::MODEL_ID);

        $this->session->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(
                [sprintf(FieldSessionStorage::SESSION_ID_FIELD_NAME, self::MODEL_ID)],
                [sprintf(FieldSessionStorage::SESSION_ID_FIELD_TYPE, self::MODEL_ID)]
            )
            ->willReturnOnConsecutiveCalls(self::FIELD_NAME, self::FIELD_TYPE);

        $expectedInfo = [
            self::FIELD_NAME,
            self::FIELD_TYPE
        ];

        $this->assertEquals($expectedInfo, $this->storage->getFieldInfo($entityConfigModel));
    }

    public function absentFieldInfoDataProvider(): array
    {
        return [
            [
                'fieldName' => null,
                'fieldType' => null,
            ],
            [
                'fieldName' => null,
                'fieldType' => self::FIELD_TYPE
            ],
            [
                'fieldName' => self::FIELD_NAME,
                'fieldType' => null,
            ]
        ];
    }

    /**
     * @dataProvider absentFieldInfoDataProvider
     */
    public function testHasFieldInfoWhenInfoIsAbsent(?string $fieldName, ?string $fieldType): void
    {
        $entityConfigModel = $this->createMock(EntityConfigModel::class);
        $entityConfigModel->expects($this->exactly(2))
            ->method('getId')
            ->willReturn(self::MODEL_ID);

        $this->session->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(
                [sprintf(FieldSessionStorage::SESSION_ID_FIELD_NAME, self::MODEL_ID)],
                [sprintf(FieldSessionStorage::SESSION_ID_FIELD_TYPE, self::MODEL_ID)]
            )
            ->willReturnOnConsecutiveCalls($fieldName, $fieldType);

        $this->assertFalse($this->storage->hasFieldInfo($entityConfigModel));
    }

    public function testHasFieldInfoWhenInfoExists(): void
    {
        $entityConfigModel = $this->createMock(EntityConfigModel::class);
        $entityConfigModel->expects($this->exactly(2))
            ->method('getId')
            ->willReturn(self::MODEL_ID);

        $this->session->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(
                [sprintf(FieldSessionStorage::SESSION_ID_FIELD_NAME, self::MODEL_ID)],
                [sprintf(FieldSessionStorage::SESSION_ID_FIELD_TYPE, self::MODEL_ID)]
            )
            ->willReturnOnConsecutiveCalls(self::FIELD_NAME, self::FIELD_TYPE);

        $this->assertTrue($this->storage->hasFieldInfo($entityConfigModel));
    }

    public function testSaveFieldInfo(): void
    {
        $entityConfigModel = $this->createMock(EntityConfigModel::class);
        $entityConfigModel->expects($this->exactly(2))
            ->method('getId')
            ->willReturn(self::MODEL_ID);

        $this->session->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                [sprintf(FieldSessionStorage::SESSION_ID_FIELD_NAME, self::MODEL_ID), self::FIELD_NAME],
                [sprintf(FieldSessionStorage::SESSION_ID_FIELD_TYPE, self::MODEL_ID), self::FIELD_TYPE]
            )
            ->willReturnOnConsecutiveCalls(self::FIELD_NAME, self::FIELD_TYPE);

        $this->storage->saveFieldInfo($entityConfigModel, self::FIELD_NAME, self::FIELD_TYPE);
    }
}
