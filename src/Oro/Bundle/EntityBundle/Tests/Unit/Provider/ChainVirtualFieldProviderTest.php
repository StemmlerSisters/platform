<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Provider;

use Oro\Bundle\EntityBundle\Provider\ChainVirtualFieldProvider;
use Oro\Bundle\EntityBundle\Provider\VirtualFieldProviderInterface;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub\ReturnCallback;
use PHPUnit\Framework\TestCase;

class ChainVirtualFieldProviderTest extends TestCase
{
    /** @var VirtualFieldProviderInterface[]&MockObject[] */
    private array $providers = [];
    private ConfigProvider&MockObject $configProvider;
    private ChainVirtualFieldProvider $chainProvider;

    #[\Override]
    protected function setUp(): void
    {
        $highPriorityProvider = $this->createMock(VirtualFieldProviderInterface::class);
        $lowPriorityProvider = $this->createMock(VirtualFieldProviderInterface::class);
        $this->providers = [$highPriorityProvider, $lowPriorityProvider];
        $this->configProvider = $this->createMock(ConfigProvider::class);

        $this->chainProvider = new ChainVirtualFieldProvider($this->providers, $this->configProvider);
    }

    public function testIsVirtualFieldByLowPriorityProvider(): void
    {
        $this->providers[0]->expects($this->once())
            ->method('isVirtualField')
            ->with('testClass', 'testField')
            ->willReturn(true);
        $this->providers[1]->expects($this->never())
            ->method('isVirtualField');

        $this->assertTrue($this->chainProvider->isVirtualField('testClass', 'testField'));
    }

    public function testIsVirtualFieldByHighPriorityProvider(): void
    {
        $this->providers[0]->expects($this->once())
            ->method('isVirtualField')
            ->with('testClass', 'testField')
            ->willReturn(false);
        $this->providers[1]->expects($this->once())
            ->method('isVirtualField')
            ->with('testClass', 'testField')
            ->willReturn(true);

        $this->assertTrue($this->chainProvider->isVirtualField('testClass', 'testField'));
    }

    public function testIsVirtualFieldNone(): void
    {
        $this->providers[0]->expects($this->once())
            ->method('isVirtualField')
            ->with('testClass', 'testField')
            ->willReturn(false);
        $this->providers[1]->expects($this->once())
            ->method('isVirtualField')
            ->with('testClass', 'testField')
            ->willReturn(false);

        $this->assertFalse($this->chainProvider->isVirtualField('testClass', 'testField'));
    }

    public function testIsVirtualFieldWithoutChildProviders(): void
    {
        $chainProvider = new ChainVirtualFieldProvider([], $this->configProvider);
        $this->assertFalse($chainProvider->isVirtualField('testClass', 'testField'));
    }

    public function testGetVirtualFields(): void
    {
        $entityClass = 'testClass';

        $this->configProvider->expects($this->once())
            ->method('hasConfig')
            ->with($entityClass)
            ->willReturn(false);
        $this->configProvider->expects($this->never())
            ->method('getConfig');

        $this->providers[0]->expects($this->once())
            ->method('getVirtualFields')
            ->with($entityClass)
            ->willReturn(['testField1', 'testField2']);
        $this->providers[1]->expects($this->once())
            ->method('getVirtualFields')
            ->with($entityClass)
            ->willReturn(['testField1', 'testField3']);

        $this->assertEquals(
            ['testField1', 'testField2', 'testField3'],
            $this->chainProvider->getVirtualFields($entityClass)
        );
    }

    public function testGetVirtualFieldsForNotAccessibleEntity(): void
    {
        $entityClass = 'testClass';

        $entityConfig = new Config(
            $this->createMock(ConfigIdInterface::class),
            ['is_extend' => true, 'state' => ExtendScope::STATE_NEW]
        );
        $this->configProvider->expects($this->once())
            ->method('hasConfig')
            ->with($entityClass)
            ->willReturn(true);
        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with($entityClass)
            ->willReturn($entityConfig);

        $this->providers[0]->expects($this->never())
            ->method('getVirtualFields');
        $this->providers[1]->expects($this->never())
            ->method('getVirtualFields');

        $this->assertSame(
            [],
            $this->chainProvider->getVirtualFields($entityClass)
        );
    }

    public function testGetVirtualFieldsWithoutChildProviders(): void
    {
        $chainProvider = new ChainVirtualFieldProvider([], $this->configProvider);
        $this->assertSame([], $chainProvider->getVirtualFields('testClass'));
    }

    public function testGetVirtualFieldQuery(): void
    {
        $fieldsConfig = [
            'testClass0' => [
                'testField0-1' => [
                    'select' => ['expr' => 'test.name', 'return_type' => 'string'],
                    'join'   => ['left' => [['join' => 'entity.test', 'alias' => 'test']]]
                ],
            ],
            'testClass1' => [
                'testField1-1' => [
                    'select' => ['expr' => 'test.name', 'return_type' => 'string'],
                    'join'   => ['left' => [['join' => 'entity.test', 'alias' => 'test']]]
                ]
            ]
        ];

        $this->addQueryMock($fieldsConfig);

        $this->assertEquals(
            $fieldsConfig['testClass0']['testField0-1'],
            $this->chainProvider->getVirtualFieldQuery('testClass0', 'testField0-1')
        );

        $this->assertEquals(
            $fieldsConfig['testClass1']['testField1-1'],
            $this->chainProvider->getVirtualFieldQuery('testClass1', 'testField1-1')
        );

        try {
            $this->chainProvider->getVirtualFieldQuery('testClass1', 'testField1-2');
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(
                'A query for field "testField1-2" in class "testClass1" was not found.',
                $e->getMessage()
            );
        }
    }

    /**
     * Mocks for getVirtualFieldQuery method
     */
    protected function addQueryMock($fieldsConfig)
    {
        $providers = $this->providers;
        foreach ($providers as $idx => $provider) {
            $fields = $fieldsConfig['testClass' . $idx];

            $with = [];
            $willForIsVirtualField = [];
            $willForGetVirtualFieldQuery = [];
            foreach ($fields as $fieldName => $fieldConfig) {
                $with[] = ['testClass' . $idx, $fieldName];
                $willForIsVirtualField[] = new ReturnCallback(function () use ($fieldsConfig, $idx, $fieldName) {
                    return isset($fieldsConfig['testClass' . $idx][$fieldName]);
                });
                $willForGetVirtualFieldQuery[] = $fieldsConfig['testClass' . $idx][$fieldName];
            }
            $provider->expects($this->atLeastOnce())
                ->method('isVirtualField')
                ->withConsecutive(...$with)
                ->willReturnOnConsecutiveCalls(...$willForIsVirtualField);
            $provider->expects($this->atLeastOnce())
                ->method('getVirtualFieldQuery')
                ->withConsecutive(...$with)
                ->willReturnOnConsecutiveCalls(...$willForGetVirtualFieldQuery);
        }
    }

    public function testGetVirtualFieldQueryWithoutChildProviders(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A query for field "testField" in class "testClass" was not found.');

        $chainProvider = new ChainVirtualFieldProvider([], $this->configProvider);
        $chainProvider->getVirtualFieldQuery('testClass', 'testField');
    }
}
