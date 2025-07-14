<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\WorkflowBundle\Exception\InvalidTransitionException;
use Oro\Bundle\WorkflowBundle\Model\Step;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Model\TransitionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransitionManagerTest extends TestCase
{
    private TransitionManager $transitionManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->transitionManager = new TransitionManager();
    }

    public function testGetTransitionsEmpty(): void
    {
        $this->assertInstanceOf(
            ArrayCollection::class,
            $this->transitionManager->getTransitions()
        );
    }

    public function testGetTransition(): void
    {
        $transition = $this->getTransitionMock('transition');

        $this->transitionManager->setTransitions([$transition]);

        $this->assertEquals($transition, $this->transitionManager->getTransition('transition'));
    }

    /**
     * @dataProvider getStartTransitionDataProvider
     *
     * @param string $name
     * @param array $transitions
     * @param Transition|null $expected
     */
    public function testGetStartTransition($name, array $transitions, ?Transition $expected = null): void
    {
        $this->transitionManager->setTransitions($transitions);

        $this->assertEquals($expected, $this->transitionManager->getStartTransition($name));
    }

    public function getStartTransitionDataProvider(): \Generator
    {
        $transition = $this->getTransitionMock('test_transition');
        $startTransition = $this->getTransitionMock('test_start_transition', true);
        $defaultStartTransition = $this->getTransitionMock(TransitionManager::DEFAULT_START_TRANSITION_NAME, true);

        yield 'invalid name' => [
            'name' => 10,
            'transitions' => [$transition, $startTransition, $defaultStartTransition],
            'expected' => null
        ];

        yield 'empty name' => [
            'name' => '',
            'transitions' => [$transition, $startTransition],
            'expected' => null
        ];

        yield 'empty name with default transition' => [
            'name' => '',
            'transitions' => [$transition, $startTransition, $defaultStartTransition],
            'expected' => $defaultStartTransition
        ];

        yield 'invalid string name' => [
            'name' => 'invalid_transition_name',
            'transitions' => [$transition, $startTransition, $defaultStartTransition],
            'expected' => $defaultStartTransition
        ];

        yield 'string name and not start transition' => [
            'name' => 'test_transition',
            'transitions' => [$transition, $startTransition, $defaultStartTransition],
            'expected' => null
        ];

        yield 'string name and start transition' => [
            'name' => 'test_start_transition',
            'transitions' => [$transition, $startTransition, $defaultStartTransition],
            'expected' => $startTransition
        ];
    }

    public function testSetTransitions(): void
    {
        $transitionOne = $this->getTransitionMock('transition1');
        $transitionTwo = $this->getTransitionMock('transition2');

        $this->transitionManager->setTransitions([$transitionOne, $transitionTwo]);
        $transitions = $this->transitionManager->getTransitions();
        $this->assertInstanceOf(ArrayCollection::class, $transitions);
        $expected = ['transition1' => $transitionOne, 'transition2' => $transitionTwo];
        $this->assertEquals($expected, $transitions->toArray());

        $transitionsCollection = new ArrayCollection(
            ['transition1' => $transitionOne, 'transition2' => $transitionTwo]
        );
        $this->transitionManager->setTransitions($transitionsCollection);
        $transitions = $this->transitionManager->getTransitions();
        $this->assertInstanceOf(ArrayCollection::class, $transitions);
        $expected = ['transition1' => $transitionOne, 'transition2' => $transitionTwo];
        $this->assertEquals($expected, $transitions->toArray());
    }

    private function getTransitionMock(string $name, bool $isStart = false, ?Step $step = null): Transition&MockObject
    {
        $transition = $this->createMock(Transition::class);
        $transition->expects($this->any())
            ->method('getName')
            ->willReturn($name);
        if ($isStart) {
            $transition->expects($this->any())
                ->method('isStart')
                ->willReturn($isStart);
        }
        if ($step) {
            $transition->expects($this->any())
                ->method('getStepTo')
                ->willReturn($step);
        }

        return $transition;
    }

    public function testGetStartTransitions(): void
    {
        $allowedStartTransition = $this->getTransitionMock('test_start', true);
        $allowedTransition = $this->getTransitionMock('test', false);

        $transitions = new ArrayCollection(
            [
                $allowedStartTransition,
                $allowedTransition
            ]
        );
        $expected = new ArrayCollection(['test_start' => $allowedStartTransition]);

        $this->transitionManager->setTransitions($transitions);
        $this->assertEquals($expected, $this->transitionManager->getStartTransitions());
    }

    public function testExtractTransitionException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected transition argument type is string or Transition, but stdClass given');

        $transition = new \stdClass();
        $this->transitionManager->extractTransition($transition);
    }

    public function testExtractTransitionStringUnknown(): void
    {
        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage('Transition "test" is not exist in workflow.');

        $transition = 'test';
        $this->transitionManager->extractTransition($transition);
    }

    public function testExtractTransition(): void
    {
        $transition = $this->getTransitionMock('test');
        $this->assertSame($transition, $this->transitionManager->extractTransition($transition));
    }

    public function testExtractTransitionString(): void
    {
        $transitionName = 'test';
        $transition = $this->getTransitionMock($transitionName);
        $this->transitionManager->setTransitions([$transition]);

        $this->assertSame($transition, $this->transitionManager->extractTransition($transitionName));
    }

    public function testGetDefaultStartTransition(): void
    {
        $this->assertNull($this->transitionManager->getDefaultStartTransition());

        $transition = $this->getTransitionMock(TransitionManager::DEFAULT_START_TRANSITION_NAME);

        $this->transitionManager->setTransitions(new ArrayCollection([$transition]));
        $this->assertEquals($transition, $this->transitionManager->getDefaultStartTransition());
    }
}
