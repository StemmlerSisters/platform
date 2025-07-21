<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\EventListener;

use Oro\Bundle\SecurityBundle\Authorization\RequestAuthorizationChecker;
use Oro\Bundle\SecurityBundle\EventListener\ApiEventListener;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Bundle\SoapBundle\Event\FindAfter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ApiEventListenerTest extends TestCase
{
    private RequestAuthorizationChecker&MockObject $requestAuthorizationChecker;
    private AclHelper&MockObject $aclHelper;
    private Request $request;
    private RequestStack $requestStack;
    private ApiEventListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->requestAuthorizationChecker = $this->createMock(RequestAuthorizationChecker::class);
        $this->aclHelper = $this->createMock(AclHelper::class);
        $this->request = new Request();
        $this->requestStack = new RequestStack();

        $this->listener = new ApiEventListener(
            $this->requestAuthorizationChecker,
            $this->aclHelper,
            $this->requestStack
        );
    }

    /**
     * @dataProvider onFindAfterProvider
     */
    public function testOnFindAfter(int $isGranted, bool $throwException): void
    {
        $this->requestStack->push($this->request);
        $object = new \stdClass();
        $this->requestAuthorizationChecker->expects($this->once())
            ->method('isRequestObjectIsGranted')
            ->with($this->request, $object)
            ->willReturn($isGranted);

        if ($throwException) {
            $this->expectException(AccessDeniedException::class);
        }
        $event = new FindAfter($object);
        $this->listener->onFindAfter($event);
    }

    public function onFindAfterProvider(): array
    {
        return [
            [-1, true],
            [0, false],
            [1, false],
        ];
    }

    public function testOnFindAfterNoRequest(): void
    {
        $this->requestAuthorizationChecker->expects($this->never())
            ->method($this->anything());

        $object = new \stdClass();
        $event = new FindAfter($object);
        $this->listener->onFindAfter($event);
    }
}
