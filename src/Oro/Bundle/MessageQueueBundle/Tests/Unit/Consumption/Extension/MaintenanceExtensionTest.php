<?php

namespace Oro\Bundle\MessageQueueBundle\Tests\Unit\Consumption\Extension;

use Oro\Bundle\MaintenanceBundle\Maintenance\MaintenanceModeState;
use Oro\Bundle\MessageQueueBundle\Consumption\Extension\MaintenanceExtension;
use Oro\Component\MessageQueue\Consumption\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MaintenanceExtensionTest extends TestCase
{
    private MaintenanceModeState&MockObject $maintenance;
    private MaintenanceExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->maintenance = $this->createMock(MaintenanceModeState::class);

        $this->extension = new MaintenanceExtension($this->maintenance, 1);
    }

    public function testShouldDoNothingIfMaintenanceModIsOff(): void
    {
        $context = $this->createMock(Context::class);

        $this->maintenance->expects(self::once())
            ->method('isOn')
            ->willReturn(false);
        $context->expects(self::never())
            ->method('setExecutionInterrupted');
        $context->expects(self::never())
            ->method('setInterruptedReason');

        $this->extension->onBeforeReceive($context);
    }

    public function testShouldSleepAnInterruptedMaintenanceModIsOn(): void
    {
        $context = $this->createMock(Context::class);
        $logger = $this->createMock(LoggerInterface::class);

        $context->expects(self::atLeastOnce())
            ->method('getLogger')
            ->willReturn($logger);

        $logger->expects(self::once())
            ->method('notice')
            ->with('The maintenance mode has been activated.');
        $logger->expects(self::once())
            ->method('info')
            ->with('Waiting for the maintenance mode deactivation.');

        $this->maintenance->expects(self::exactly(2))
            ->method('isOn')
            ->willReturnOnConsecutiveCalls(true, false);
        $context->expects(self::once())
            ->method('setExecutionInterrupted')
            ->with(true);
        $context->expects(self::once())
            ->method('setInterruptedReason')
            ->with('The Maintenance mode has been deactivated.');

        $this->extension->onBeforeReceive($context);
    }
}
