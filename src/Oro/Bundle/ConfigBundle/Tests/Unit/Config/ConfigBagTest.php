<?php

namespace Oro\Bundle\ConfigBundle\Tests\Unit\Config;

use Oro\Bundle\ConfigBundle\Config\ConfigBag;
use Oro\Bundle\ConfigBundle\Config\DataTransformerInterface;
use Oro\Bundle\ConfigBundle\DependencyInjection\SystemConfiguration\ProcessorDecorator;
use Oro\Bundle\ConfigBundle\Exception\UnexpectedTypeException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ConfigBagTest extends TestCase
{
    private ContainerInterface&MockObject $container;

    #[\Override]
    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
    }

    private function createConfigBag(array $config): ConfigBag
    {
        return new ConfigBag($config, $this->container);
    }

    public function testGetConfig(): void
    {
        $config = ['key' => 'value'];

        $configBag = $this->createConfigBag($config);

        $this->assertEquals($config, $configBag->getConfig());
    }

    public function testGetDataTransformerWhenContainerReturnsNull(): void
    {
        $this->container->expects($this->once())
            ->method('get')
            ->with('test.service')
            ->willReturn(null);

        $config = [
            'fields' => [
                'test_key' => [
                    'data_transformer' => 'test.service'
                ]
            ]
        ];
        $configBag = $this->createConfigBag($config);

        $this->assertEquals(null, $configBag->getDataTransformer('test_key'));
    }

    public function testGetDataTransformerWhenServiceNotFound(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        $this->container->expects($this->once())
            ->method('get')
            ->with('test.service')
            ->willThrowException(new ServiceNotFoundException('test.service'));

        $config = [
            'fields' => [
                'test_key' => [
                    'data_transformer' => 'test.service'
                ]
            ]
        ];
        $configBag = $this->createConfigBag($config);

        $this->assertEquals(null, $configBag->getDataTransformer('test_key'));
    }

    public function testGetDataTransformer(): void
    {
        $transformer = $this->createMock(DataTransformerInterface::class);
        $this->container->expects($this->once())
            ->method('get')
            ->with('test.service')
            ->willReturn($transformer);

        $config = [
            'fields' => [
                'test_key' => [
                    'data_transformer' => 'test.service'
                ]
            ]
        ];
        $configBag = $this->createConfigBag($config);

        $this->assertSame($transformer, $configBag->getDataTransformer('test_key'));
    }

    public function testGetDataTransformerWithUnexpectedType(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage(sprintf('Expected argument of type "%s"', DataTransformerInterface::class));

        $this->container->expects($this->once())
            ->method('get')
            ->with('test.service')
            ->willReturn(new \stdClass());

        $config = [
            'fields' => [
                'test_key' => [
                    'data_transformer' => 'test.service'
                ]
            ]
        ];
        $configBag = $this->createConfigBag($config);

        $configBag->getDataTransformer('test_key');
    }

    public function testGetFieldsRootWhenFieldsRootDoesNotExist(): void
    {
        $config = [];
        $configBag = $this->createConfigBag($config);

        $this->assertFalse($configBag->getFieldsRoot('test'));
    }

    public function testGetFieldsRootWhenFieldsRootExists(): void
    {
        $config = [
            ProcessorDecorator::FIELDS_ROOT => [
                'test' => 'value'
            ]
        ];
        $configBag = $this->createConfigBag($config);

        $this->assertEquals('value', $configBag->getFieldsRoot('test'));
    }

    public function testGetTreeRootWhenTreeRootDoesNotExist(): void
    {
        $config = [];
        $configBag = $this->createConfigBag($config);

        $this->assertFalse($configBag->getTreeRoot('test'));
    }

    public function testGetTreeRootWhenTreeRootExists(): void
    {
        $config = [
            ProcessorDecorator::TREE_ROOT => [
                'test' => 'value'
            ]
        ];
        $configBag = $this->createConfigBag($config);

        $this->assertEquals('value', $configBag->getTreeRoot('test'));
    }

    public function testGetGroupsNodeWhenGroupsNodeDoesNotExist(): void
    {
        $config = [];
        $configBag = $this->createConfigBag($config);

        $this->assertFalse($configBag->getGroupsNode('test'));
    }

    public function testGetGroupsNodeWhenGroupsNodeExists(): void
    {
        $config = [
            ProcessorDecorator::GROUPS_NODE => [
                'test' => 'value'
            ]
        ];
        $configBag = $this->createConfigBag($config);

        $this->assertEquals('value', $configBag->getGroupsNode('test'));
    }
}
