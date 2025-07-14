<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Tools;

use Oro\Bundle\EntityBundle\Exception\NotManageableEntityException;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\EntityBundle\Tools\EntityClassNameHelper;
use Oro\Bundle\EntityBundle\Tools\EntityRoutingHelper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class EntityRoutingHelperTest extends TestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private EntityRoutingHelper $entityRoutingHelper;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->entityRoutingHelper = new EntityRoutingHelper(
            new EntityClassNameHelper($this->createMock(EntityAliasResolver::class)),
            $this->doctrineHelper,
            $this->urlGenerator
        );
    }

    /**
     * @dataProvider getUrlSafeClassNameProvider
     */
    public function testGetUrlSafeClassName(string $src, string $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->entityRoutingHelper->getUrlSafeClassName($src)
        );
    }

    /**
     * @dataProvider resolveEntityClassProvider
     */
    public function testResolveEntityClass(string $src, string $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->entityRoutingHelper->resolveEntityClass($src)
        );
    }

    public function getUrlSafeClassNameProvider(): array
    {
        return [
            ['Acme\Bundle\TestClass', 'Acme_Bundle_TestClass'],
            ['Acme_Bundle_TestClass', 'Acme_Bundle_TestClass'],
        ];
    }

    public function resolveEntityClassProvider(): array
    {
        return [
            ['Acme_Bundle_TestClass', 'Acme\Bundle\TestClass'],
            ['Acme\Bundle\TestClass', 'Acme\Bundle\TestClass'],
            [ExtendHelper::ENTITY_NAMESPACE . 'TestClass', ExtendHelper::ENTITY_NAMESPACE . 'TestClass'],
            [
                str_replace('\\', '_', ExtendHelper::ENTITY_NAMESPACE) . 'TestClass',
                ExtendHelper::ENTITY_NAMESPACE . 'TestClass'
            ],
            [
                str_replace('\\', '_', ExtendHelper::ENTITY_NAMESPACE) . 'Test_Class',
                ExtendHelper::ENTITY_NAMESPACE . 'Test_Class'
            ],
        ];
    }

    public function testGetAction(): void
    {
        $action = 'test';
        $request = new Request([EntityRoutingHelper::PARAM_ACTION => $action]);
        $this->assertEquals(
            $action,
            $this->entityRoutingHelper->getAction($request)
        );
    }

    public function testGetActionNotSpecified(): void
    {
        $request = new Request();
        $this->assertNull($this->entityRoutingHelper->getAction($request));
    }

    public function testGetEntityClassName(): void
    {
        $request = new Request([EntityRoutingHelper::PARAM_ENTITY_CLASS => 'Acme_Bundle_TestClass']);
        $this->assertEquals(
            'Acme\Bundle\TestClass',
            $this->entityRoutingHelper->getEntityClassName($request)
        );
    }

    public function testGetEntityClassNameWithOtherParamName(): void
    {
        $paramName = 'some_entity';
        $request = new Request([$paramName => 'Acme_Bundle_TestClass']);
        $this->assertEquals(
            'Acme\Bundle\TestClass',
            $this->entityRoutingHelper->getEntityClassName($request, $paramName)
        );
    }

    public function testGetEntityClassNameNotSpecified(): void
    {
        $request = new Request();
        $this->assertNull($this->entityRoutingHelper->getEntityClassName($request));
    }

    public function testGetEntityId(): void
    {
        $request = new Request([EntityRoutingHelper::PARAM_ENTITY_ID => '123']);
        $this->assertEquals(
            '123',
            $this->entityRoutingHelper->getEntityId($request)
        );
    }

    public function testGetEntityIdWithOtherParamName(): void
    {
        $paramName = 'some_entity';
        $request = new Request([$paramName => '123']);
        $this->assertEquals(
            '123',
            $this->entityRoutingHelper->getEntityId($request, $paramName)
        );
    }

    public function testGetEntityIdNotSpecified(): void
    {
        $request = new Request();
        $this->assertNull($this->entityRoutingHelper->getEntityId($request));
    }

    public function testGetRouteParameters(): void
    {
        $this->assertEquals(
            [
                EntityRoutingHelper::PARAM_ENTITY_CLASS => 'Acme_Bundle_TestClass',
                EntityRoutingHelper::PARAM_ENTITY_ID    => '123',
                EntityRoutingHelper::PARAM_ACTION       => 'test'
            ],
            $this->entityRoutingHelper->getRouteParameters('Acme\Bundle\TestClass', 123, 'test')
        );
    }

    public function testGetRouteParametersWithoutAction(): void
    {
        $this->assertEquals(
            [
                EntityRoutingHelper::PARAM_ENTITY_CLASS => 'Acme_Bundle_TestClass',
                EntityRoutingHelper::PARAM_ENTITY_ID    => '123'
            ],
            $this->entityRoutingHelper->getRouteParameters('Acme\Bundle\TestClass', 123)
        );
    }

    public function testGenerateUrl(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'test_route',
                [
                    EntityRoutingHelper::PARAM_ENTITY_CLASS => 'Acme_Bundle_TestClass',
                    EntityRoutingHelper::PARAM_ENTITY_ID    => '123',
                    'param1'                                => 'test'
                ]
            )
            ->willReturn('test_url');

        $this->assertEquals(
            'test_url',
            $this->entityRoutingHelper->generateUrl('test_route', 'Acme\Bundle\TestClass', 123, ['param1' => 'test'])
        );
    }

    public function testGenerateUrlByRequest(): void
    {
        $request = new Request(
            [
                EntityRoutingHelper::PARAM_ENTITY_CLASS => 'Acme_Bundle_TestClass',
                EntityRoutingHelper::PARAM_ENTITY_ID    => 123
            ]
        );

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'test_route',
                [
                    EntityRoutingHelper::PARAM_ENTITY_CLASS => 'Acme_Bundle_TestClass',
                    EntityRoutingHelper::PARAM_ENTITY_ID    => '123',
                    'param1'                                => 'test'
                ]
            )
            ->willReturn('test_url');

        $this->assertEquals(
            'test_url',
            $this->entityRoutingHelper->generateUrlByRequest('test_route', $request, ['param1' => 'test'])
        );
    }

    public function testGenerateUrlByRequestNoEntityClass(): void
    {
        $request = new Request();

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'test_route',
                [
                    'param1' => 'test'
                ]
            )
            ->willReturn('test_url');

        $this->assertEquals(
            'test_url',
            $this->entityRoutingHelper->generateUrlByRequest('test_route', $request, ['param1' => 'test'])
        );
    }

    public function testGetEntity(): void
    {
        $entity = new \stdClass();

        $this->doctrineHelper->expects($this->once())
            ->method('getEntity')
            ->with('Acme\Bundle\TestClass', 123)
            ->willReturn($entity);

        $this->assertSame(
            $entity,
            $this->entityRoutingHelper->getEntity('Acme_Bundle_TestClass', 123)
        );
    }

    public function testGetEntityForNotManageableEntity(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Entity class "Acme\Bundle\TestClass" is not manageable.');

        $this->doctrineHelper->expects($this->once())
            ->method('getEntity')
            ->with('Acme\Bundle\TestClass', 123)
            ->willThrowException(new NotManageableEntityException('Acme\Bundle\TestClass'));

        $this->entityRoutingHelper->getEntity('Acme_Bundle_TestClass', 123);
    }

    public function testGetEntityForNotExistingEntity(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage("Record doesn't exist");

        $this->doctrineHelper->expects($this->once())
            ->method('getEntity')
            ->with('Acme\Bundle\TestClass', 123)
            ->willReturn(null);

        $this->entityRoutingHelper->getEntity('Acme_Bundle_TestClass', 123);
    }

    public function testGetEntityReference(): void
    {
        $entityReference = new \stdClass();

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityReference')
            ->with('Acme\Bundle\TestClass', 123)
            ->willReturn($entityReference);

        $this->assertSame(
            $entityReference,
            $this->entityRoutingHelper->getEntityReference('Acme_Bundle_TestClass', 123)
        );
    }

    public function testGetEntityReferenceForNewEntity(): void
    {
        $entityReference = new \stdClass();

        $this->doctrineHelper->expects($this->once())
            ->method('createEntityInstance')
            ->with('Acme\Bundle\TestClass')
            ->willReturn($entityReference);

        $this->assertSame(
            $entityReference,
            $this->entityRoutingHelper->getEntityReference('Acme_Bundle_TestClass')
        );
    }

    public function testGetEntityReferenceForNotManageableEntity(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Entity class "Acme\Bundle\TestClass" is not manageable.');

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityReference')
            ->with('Acme\Bundle\TestClass', 123)
            ->willThrowException(new NotManageableEntityException('Acme\Bundle\TestClass'));

        $this->entityRoutingHelper->getEntityReference('Acme_Bundle_TestClass', 123);
    }

    public function testGetEntityReferenceForNewNotManageableEntity(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Entity class "Acme\Bundle\TestClass" is not manageable.');

        $this->doctrineHelper->expects($this->once())
            ->method('createEntityInstance')
            ->with('Acme\Bundle\TestClass')
            ->willThrowException(new NotManageableEntityException('Acme\Bundle\TestClass'));

        $this->entityRoutingHelper->getEntityReference('Acme_Bundle_TestClass');
    }
}
