<?php

namespace Oro\Bundle\DraftBundle\Tests\Unit\Duplicator\Extension;

use DeepCopy\Matcher\PropertyTypeMatcher;
use Oro\Bundle\DraftBundle\Duplicator\DraftContext;
use Oro\Bundle\DraftBundle\Duplicator\Extension\DateTimeExtension;
use Oro\Bundle\DraftBundle\Duplicator\Filter\DateTimeFilter;
use Oro\Bundle\DraftBundle\Manager\DraftManager;
use Oro\Bundle\DraftBundle\Tests\Unit\Stub\DraftableEntityStub;
use Oro\Bundle\SecurityBundle\Tools\UUIDGenerator;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class DateTimeExtensionTest extends TestCase
{
    use  EntityTrait;

    private DateTimeExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->extension = new DateTimeExtension();
    }

    public function testGetFilterWithDraftSource(): void
    {
        $context = new DraftContext();
        $context->offsetSet(
            'source',
            $this->getEntity(DraftableEntityStub::class, ['draftUuid' => UUIDGenerator::v4()])
        );
        $this->extension->setContext($context);

        $this->assertEquals(new DateTimeFilter(), $this->extension->getFilter());
    }

    public function testGetFilterWithoutDraftSource(): void
    {
        $context = new DraftContext();
        $context->offsetSet('source', $this->getEntity(DraftableEntityStub::class));
        $this->extension->setContext($context);

        $this->assertEquals(new DateTimeFilter(), $this->extension->getFilter());
    }

    public function testGetMatcher(): void
    {
        $this->assertEquals(new PropertyTypeMatcher(\DateTime::class), $this->extension->getMatcher());
    }

    public function testIsSupport(): void
    {
        $source = $this->getEntity(DraftableEntityStub::class);
        $context = new DraftContext();
        $context->offsetSet('action', DraftManager::ACTION_CREATE_DRAFT);
        $this->extension->setContext($context);

        $this->assertTrue($this->extension->isSupport($source));
    }
}
