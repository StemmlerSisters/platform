<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\Shared;

use Oro\Bundle\ApiBundle\Exception\ActionNotAllowedException;
use Oro\Bundle\ApiBundle\Processor\Subresource\Shared\ValidateIsCollection;
use Oro\Bundle\ApiBundle\Request\ApiAction;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\GetSubresourceProcessorTestCase;

class ValidateIsCollectionTest extends GetSubresourceProcessorTestCase
{
    private ValidateIsCollection $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new ValidateIsCollection();
    }

    public function testProcessWhenIsCollectionFlagIsTrue(): void
    {
        $this->context->setIsCollection(true);
        $this->processor->process($this->context);
    }

    public function testProcessWhenIsCollectionFlagIsFalse(): void
    {
        $this->expectException(ActionNotAllowedException::class);
        $this->context->setAction(ApiAction::ADD_RELATIONSHIP);
        $this->context->setParentClassName('Test\Class');
        $this->context->setAssociationName('test');
        $this->processor->process($this->context);
    }
}
