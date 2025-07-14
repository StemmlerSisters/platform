<?php

namespace Oro\Bundle\UIBundle\Tests\Unit\Placeholder;

use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\UIBundle\Placeholder\PlaceholderConfigurationProvider;
use Oro\Bundle\UIBundle\Placeholder\PlaceholderProvider;
use Oro\Component\Config\Resolver\ResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PlaceholderProviderTest extends TestCase
{
    private const TEST_PLACEHOLDER = 'test_placeholder';

    private ResolverInterface&MockObject $resolver;
    private AuthorizationCheckerInterface&MockObject $authorizationChecker;
    private FeatureChecker&MockObject $featureChecker;

    #[\Override]
    protected function setUp(): void
    {
        $this->resolver = $this->createMock(ResolverInterface::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->featureChecker = $this->createMock(FeatureChecker::class);

        $this->featureChecker->expects($this->any())
            ->method('isResourceEnabled')
            ->willReturn(true);
    }

    public function testOnlyTemplateDefined(): void
    {
        $items = [
            'placeholder_item' => [
                'template' => 'template'
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);

        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($items['placeholder_item'], $variables)
            ->willReturn($items['placeholder_item']);
        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);

        $this->assertSame(
            [['template' => 'template']],
            $actual
        );
    }

    public function testTemplateAndDataDefined(): void
    {
        $items = [
            'placeholder_item' => [
                'template' => 'template',
                'data'     => '@service->getData($entity$)'
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($items['placeholder_item'], $variables)
            ->willReturn($items['placeholder_item']);

        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);

        $this->assertSame(
            [$items['placeholder_item']],
            $actual
        );
    }

    public function testApplicableStringConditionSuccess(): void
    {
        $items = [
            'placeholder_item' => [
                'template'   => 'template',
                'applicable' => '@service1->isApplicable($entity$)'
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);
        $config1 = ['applicable' => $items['placeholder_item']['applicable']];
        unset($items['placeholder_item']['applicable']);
        $config2 = $items['placeholder_item'];
        $this->resolver->expects($this->exactly(2))
            ->method('resolve')
            ->withConsecutive(
                [$config1, $variables],
                [$config2, $variables]
            )
            ->willReturnOnConsecutiveCalls(
                ['applicable' => true],
                $config2
            );

        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);

        $this->assertSame(
            [$items['placeholder_item']],
            $actual
        );
    }

    public function testApplicableArrayConditionsSuccess(): void
    {
        $items = [
            'placeholder_item' => [
                'template'   => 'template',
                'applicable' => ['@service1->isApplicable($entity$)', '@service1->isApplicable($entity$)']
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);
        $config1 = ['applicable' => $items['placeholder_item']['applicable'][0]];
        $config2 = ['applicable' => $items['placeholder_item']['applicable'][1]];
        unset($items['placeholder_item']['applicable']);
        $config3 = $items['placeholder_item'];
        $this->resolver->expects($this->exactly(3))
            ->method('resolve')
            ->withConsecutive(
                [$config1, $variables],
                [$config2, $variables],
                [$config3, $variables]
            )
            ->willReturnOnConsecutiveCalls(
                ['applicable' => true],
                ['applicable' => true],
                $config3
            );

        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);

        $this->assertSame(
            [$items['placeholder_item']],
            $actual
        );
    }

    public function testApplicableArrayConditionsFail(): void
    {
        $items = [
            'placeholder_item' => [
                'template'   => 'template',
                'applicable' => ['@service1->isApplicable($entity$)', '@service1->isApplicable($entity$)']
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with(['applicable' => $items['placeholder_item']['applicable'][0]], $variables)
            ->willReturn(['applicable' => false]);

        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);

        $this->assertSame([], $actual);
    }

    public function testAclConditionStringSuccess(): void
    {
        $items = [
            'placeholder_item' => [
                'template' => 'template',
                'acl'      => 'acl_ancestor'
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);
        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('acl_ancestor')
            ->willReturn(true);

        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);
        unset($items['placeholder_item']['acl']);
        $this->assertSame([], $actual);
    }

    public function testAclConditionStringFail(): void
    {
        $items = [
            'placeholder_item' => [
                'template' => 'template',
                'acl'      => 'acl_ancestor'
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);
        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('acl_ancestor')
            ->willReturn(false);

        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);
        unset($items['placeholder_item']['acl']);
        $this->assertSame([], $actual);
    }

    public function testAclConditionArraySuccess(): void
    {
        $items = [
            'placeholder_item' => [
                'template' => 'template',
                'acl'      => ['acl_ancestor1', 'acl_ancestor2']
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);
        $this->authorizationChecker->expects($this->exactly(2))
            ->method('isGranted')
            ->withConsecutive(['acl_ancestor1'], ['acl_ancestor2'])
            ->willReturn(true);
        unset($items['placeholder_item']['acl']);
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($items['placeholder_item'], $variables)
            ->willReturn($items['placeholder_item']);

        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);
        unset($items['placeholder_item']['acl']);
        $this->assertSame([$items['placeholder_item']], $actual);
    }

    public function testAclConditionArrayFail(): void
    {
        $items = [
            'placeholder_item' => [
                'template' => 'template',
                'acl'      => ['acl_ancestor1', 'acl_ancestor2']
            ]
        ];

        $variables = ['foo' => 'bar'];

        $provider = $this->createProvider($items);
        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('acl_ancestor1')
            ->willReturn(false);

        $actual = $provider->getPlaceholderItems(self::TEST_PLACEHOLDER, $variables);
        unset($items['placeholder_item']['acl']);
        $this->assertSame([], $actual);
    }

    private function createProvider(array $items): PlaceholderProvider
    {
        $configProvider = $this->createMock(PlaceholderConfigurationProvider::class);
        $configProvider->expects(self::any())
            ->method('getPlaceholderItems')
            ->willReturnCallback(function ($placeholderName) use ($items) {
                return self::TEST_PLACEHOLDER === $placeholderName
                    ? array_keys($items)
                    : null;
            });
        $configProvider->expects(self::any())
            ->method('getItemConfiguration')
            ->willReturnCallback(function ($itemName) use ($items) {
                return $items[$itemName] ?? null;
            });

        return new PlaceholderProvider(
            $configProvider,
            $this->resolver,
            $this->authorizationChecker,
            $this->featureChecker
        );
    }
}
