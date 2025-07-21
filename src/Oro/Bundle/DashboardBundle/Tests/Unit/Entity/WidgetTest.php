<?php

namespace Oro\Bundle\DashboardBundle\Tests\Unit\Entity;

use Oro\Bundle\DashboardBundle\Entity\Dashboard;
use Oro\Bundle\DashboardBundle\Entity\Widget;
use PHPUnit\Framework\TestCase;

class WidgetTest extends TestCase
{
    private Widget $widget;

    #[\Override]
    protected function setUp(): void
    {
        $this->widget = new Widget();
    }

    public function testId(): void
    {
        $this->assertNull($this->widget->getId());
    }

    public function testName(): void
    {
        $this->assertNull($this->widget->getName());
        $value = 'test';
        $this->assertEquals($this->widget, $this->widget->setName($value));
        $this->assertEquals($value, $this->widget->getName());
    }

    public function testLayoutPosition(): void
    {
        $this->assertNull($this->widget->getLayoutPosition());
        $value = [1, 100];
        $this->assertEquals($this->widget, $this->widget->setLayoutPosition($value));
        $this->assertEquals($value, $this->widget->getLayoutPosition());
    }

    public function testDashboard(): void
    {
        $dashboard = $this->createMock(Dashboard::class);
        $this->assertNull($this->widget->getDashboard());
        $this->assertEquals($this->widget, $this->widget->setDashboard($dashboard));
        $this->assertEquals($dashboard, $this->widget->getDashboard());
    }

    public function testOptions(): void
    {
        $this->assertEquals([], $this->widget->getOptions());
        $options['foo'] = 'bar';
        $this->widget->setOptions($options);
        $this->assertSame($options, $this->widget->getOptions());
    }
}
