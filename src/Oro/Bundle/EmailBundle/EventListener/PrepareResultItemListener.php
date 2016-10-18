<?php

namespace Oro\Bundle\EmailBundle\EventListener;

use Oro\Bundle\EmailBundle\Entity\Email;

use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureCheckerHolderTrait;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureToggleableInterface;
use Oro\Bundle\SearchBundle\Event\PrepareResultItemEvent;
use Symfony\Component\Routing\Router;

class PrepareResultItemListener implements FeatureToggleableInterface
{
    use FeatureCheckerHolderTrait;

    /** @var Router */
    protected $router;

    /**
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Change search results view for Email entity
     *
     * @param PrepareResultItemEvent $event
     */
    public function prepareEmailItemDataEvent(PrepareResultItemEvent $event)
    {
        if (!$this->isFeaturesEnabled()) {
            return;
        }

        if ($event->getResultItem()->getEntityName() === EmailUser::ENTITY_CLASS) {
            $id = $event->getResultItem()->getEntity()->getEmail()->getId();
            $event->getResultItem()->setRecordId($id);
            $event->getResultItem()->setEntityName(Email::ENTITY_CLASS);
            $route = $this->router->generate('oro_email_thread_view', ['id' => $id], true);
            $event->getResultItem()->setRecordUrl($route);
        }
    }
}
