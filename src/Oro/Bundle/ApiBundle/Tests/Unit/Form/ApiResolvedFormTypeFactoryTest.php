<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Form;

use Oro\Bundle\ApiBundle\Form\ApiResolvedFormType;
use Oro\Bundle\ApiBundle\Form\ApiResolvedFormTypeFactory;
use Oro\Bundle\ApiBundle\Form\FormExtensionCheckerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;

class ApiResolvedFormTypeFactoryTest extends TestCase
{
    private ResolvedFormTypeFactoryInterface&MockObject $defaultFactory;
    private FormExtensionCheckerInterface&MockObject $formExtensionChecker;
    private ApiResolvedFormTypeFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->defaultFactory = $this->createMock(ResolvedFormTypeFactoryInterface::class);
        $this->formExtensionChecker = $this->createMock(FormExtensionCheckerInterface::class);

        $this->factory = new ApiResolvedFormTypeFactory(
            $this->defaultFactory,
            $this->formExtensionChecker
        );
    }

    public function testCreateResolvedTypeWhenApiFormExtensionNotActivated(): void
    {
        $type = $this->createMock(FormTypeInterface::class);
        $typeExtensions = [];
        $parent = $this->createMock(ResolvedFormTypeInterface::class);
        $resolvedType = $this->createMock(ResolvedFormTypeInterface::class);

        $this->defaultFactory->expects(self::once())
            ->method('createResolvedType')
            ->with(self::identicalTo($type), self::identicalTo($typeExtensions), self::identicalTo($parent))
            ->willReturn($resolvedType);
        $this->formExtensionChecker->expects(self::once())
            ->method('isApiFormExtensionActivated')
            ->willReturn(false);

        self::assertSame(
            $resolvedType,
            $this->factory->createResolvedType($type, $typeExtensions, $parent)
        );
    }

    public function testCreateResolvedTypeWhenApiFormExtensionActivated(): void
    {
        $type = $this->createMock(FormTypeInterface::class);
        $typeExtensions = [];
        $parent = $this->createMock(ResolvedFormTypeInterface::class);
        $resolvedType = $this->createMock(ResolvedFormTypeInterface::class);

        $this->defaultFactory->expects(self::once())
            ->method('createResolvedType')
            ->with(self::identicalTo($type), self::identicalTo($typeExtensions), self::identicalTo($parent))
            ->willReturn($resolvedType);
        $this->formExtensionChecker->expects(self::once())
            ->method('isApiFormExtensionActivated')
            ->willReturn(true);

        self::assertEquals(
            new ApiResolvedFormType($resolvedType),
            $this->factory->createResolvedType($type, $typeExtensions, $parent)
        );
    }
}
