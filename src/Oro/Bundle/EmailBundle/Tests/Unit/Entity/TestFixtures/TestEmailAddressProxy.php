<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Entity\TestFixtures;

use Oro\Bundle\EmailBundle\Entity\EmailAddress as OriginalEmailAddress;
use Oro\Bundle\EmailBundle\Entity\EmailOwnerInterface;

class TestEmailAddressProxy extends OriginalEmailAddress
{
    private ?EmailOwnerInterface $owner;

    public function __construct(?EmailOwnerInterface $owner = null)
    {
        $this->owner = $owner;
    }

    #[\Override]
    public function getOwner()
    {
        return $this->owner;
    }

    #[\Override]
    public function setOwner(?EmailOwnerInterface $owner = null)
    {
        $this->owner = $owner;

        return $this;
    }
}
