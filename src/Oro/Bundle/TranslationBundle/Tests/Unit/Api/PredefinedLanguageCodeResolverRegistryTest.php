<?php

namespace Oro\Bundle\TranslationBundle\Tests\Unit\Api;

use Oro\Bundle\TranslationBundle\Api\PredefinedLanguageCodeResolverInterface;
use Oro\Bundle\TranslationBundle\Api\PredefinedLanguageCodeResolverRegistry;
use Oro\Component\Testing\Unit\TestContainerBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PredefinedLanguageCodeResolverRegistryTest extends TestCase
{
    private PredefinedLanguageCodeResolverInterface&MockObject $resolver1;
    private PredefinedLanguageCodeResolverInterface&MockObject $resolver2;
    private PredefinedLanguageCodeResolverRegistry $registry;

    #[\Override]
    protected function setUp(): void
    {
        $this->resolver1 = $this->createMock(PredefinedLanguageCodeResolverInterface::class);
        $this->resolver2 = $this->createMock(PredefinedLanguageCodeResolverInterface::class);

        $container = TestContainerBuilder::create()
            ->add('code1', $this->resolver1)
            ->add('code2', $this->resolver2)
            ->getContainer($this);

        $this->registry = new PredefinedLanguageCodeResolverRegistry(['code1', 'code2'], $container);
    }

    public function testGetResolveWhenNoResolverForGivenValue(): void
    {
        $this->resolver1->expects(self::never())
            ->method('resolve');
        $this->resolver2->expects(self::never())
            ->method('resolve');

        self::assertNull($this->registry->resolve('code3'));
    }

    public function testGetResolveWhenResolverForGivenValueExists(): void
    {
        $this->resolver1->expects(self::once())
            ->method('resolve')
            ->willReturn('resolved_language_code_1');
        $this->resolver2->expects(self::never())
            ->method('resolve');

        self::assertEquals('resolved_language_code_1', $this->registry->resolve('code1'));
    }

    public function testGetDescriptions(): void
    {
        $this->resolver1->expects(self::once())
            ->method('getDescription')
            ->willReturn('resolver1 description');
        $this->resolver2->expects(self::once())
            ->method('getDescription')
            ->willReturn('resolver2 description');

        self::assertEquals(
            [
                'resolver1 description',
                'resolver2 description'
            ],
            $this->registry->getDescriptions()
        );
    }
}
