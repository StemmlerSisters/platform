<?php

namespace Oro\Bundle\SyncBundle\Controller;

use Oro\Bundle\SecurityBundle\Attribute\CsrfProtection;
use Oro\Bundle\SyncBundle\Authentication\Ticket\TicketProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller that allows to retrieve a new Sync authentication ticket for currently authenticated user.
 */
class TicketController extends AbstractController
{
    /**
     * Retrieve a new Sync authorize ticket for currently authenticated user.
     */
    #[Route(path: '/sync/ticket', name: 'oro_sync_ticket', methods: ['POST'])]
    #[CsrfProtection()]
    public function syncTicketAction(): JsonResponse
    {
        return new JsonResponse(
            ['ticket' => $this->container->get(TicketProvider::class)->generateTicket($this->getUser())]
        );
    }

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                TicketProvider::class,
            ]
        );
    }
}
