<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Audit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\EntityConfigBundle\Audit\Entity\ConfigLog;
use Oro\Bundle\EntityConfigBundle\Audit\Entity\ConfigLogDiff;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class ConfigLogTest extends TestCase
{
    private ConfigLog $configLog;
    private ConfigLogDiff $configLogDiff;

    #[\Override]
    protected function setUp(): void
    {
        $this->configLog = new ConfigLog();
        $this->configLogDiff = new ConfigLogDiff();
    }

    public function testConfigLog(): void
    {
        $userMock = $this->createMock(UserInterface::class);

        $this->assertEmpty($this->configLog->getId());

        $data = new \DateTime();
        $this->configLog->setLoggedAt($data);
        $this->assertEquals($data, $this->configLog->getLoggedAt());

        $this->configLog->setUser($userMock);
        $this->assertEquals($userMock, $this->configLog->getUser());

        $this->configLog->addDiff($this->configLogDiff);
        $this->assertEquals($this->configLogDiff, $this->configLog->getDiffs()->first());

        $diffsCollection = new ArrayCollection([$this->configLogDiff]);
        $this->configLog->setDiffs($diffsCollection);
        $this->assertEquals($diffsCollection, $this->configLog->getDiffs());
    }

    public function testConfigDiff(): void
    {
        $this->assertEmpty($this->configLogDiff->getId());

        $this->configLogDiff->setLog($this->configLog);
        $this->assertEquals($this->configLog, $this->configLogDiff->getLog());

        $this->configLogDiff->setClassName('className');
        $this->assertEquals('className', $this->configLogDiff->getClassName());

        $this->configLogDiff->setFieldName('fieldName');
        $this->assertEquals('fieldName', $this->configLogDiff->getFieldName());

        $this->configLogDiff->setScope('scope');
        $this->assertEquals('scope', $this->configLogDiff->getScope());

        $this->configLogDiff->setDiff(['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $this->configLogDiff->getDiff());
    }
}
