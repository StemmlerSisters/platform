<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Utils;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Entity\Status;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\TestConnector;
use Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\TestIntegrationType;
use Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\TestTwoWayConnector;
use Oro\Bundle\IntegrationBundle\Utils\FormUtils;
use PHPUnit\Framework\TestCase;

class FormUtilsTest extends TestCase
{
    private TypesRegistry $typesRegistry;
    private FormUtils $utils;

    #[\Override]
    protected function setUp(): void
    {
        $this->typesRegistry = new TypesRegistry();
        $this->utils = new FormUtils($this->typesRegistry);
    }

    public function testHasTwoWaySyncConnectors(): void
    {
        $testType = 'type2';
        $testTypeThatHasConnectors = 'type1';

        $this->typesRegistry->addChannelType($testType, new TestIntegrationType());
        $this->typesRegistry->addChannelType($testTypeThatHasConnectors, new TestIntegrationType());
        $this->typesRegistry->addConnectorType(uniqid('type'), $testType, new TestConnector());
        $this->typesRegistry->addConnectorType(uniqid('type'), $testTypeThatHasConnectors, new TestTwoWayConnector());

        $this->assertTrue($this->utils->hasTwoWaySyncConnectors($testTypeThatHasConnectors));
        $this->assertFalse($this->utils->hasTwoWaySyncConnectors($testType));
    }

    public function testWasSyncedAtLeastOnce(): void
    {
        $channel = new Channel();
        $status = new Status();
        $status->setChannel($channel)
            ->setCode(Status::STATUS_COMPLETED);

        $this->assertFalse(FormUtils::wasSyncedAtLeastOnce($channel));
        $channel->addStatus($status);

        $this->assertTrue(FormUtils::wasSyncedAtLeastOnce($channel));
    }
}
