<?php

namespace Oro\Bundle\EmailBundle\Controller\Configuration;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ConfigBundle\Provider\SystemConfigurationFormProvider;
use Oro\Bundle\EmailBundle\Autocomplete\MailboxUserSearchHandler;
use Oro\Bundle\EmailBundle\Entity\Mailbox;
use Oro\Bundle\EmailBundle\Form\Handler\MailboxHandler;
use Oro\Bundle\FormBundle\Autocomplete\Security;
use Oro\Bundle\FormBundle\Model\AutocompleteRequest;
use Oro\Bundle\UIBundle\Route\Router;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The controller for the mailboxes functionality.
 *
 * Actions in this controller are protected by MailboxAuthorizationListener because access to them is determined
 * by access to Organization entity which is not even always available.
 * @see \Oro\Bundle\EmailBundle\EventListener\MailboxAuthorizationListener
 */
class MailboxController extends AbstractController
{
    private const ACTIVE_GROUP = 'platform';
    private const ACTIVE_SUBGROUP = 'email_configuration';

    #[Route(path: '/mailbox/update/{id}', name: 'oro_email_mailbox_update')]
    #[Template('@OroEmail/Configuration/Mailbox/update.html.twig')]
    public function updateAction(
        #[MapEntity]
        Mailbox $mailbox,
        Request $request
    ): array|RedirectResponse {
        return $this->update($mailbox, $request);
    }

    /**
     * Prepares and handles data of Mailbox update/create form.
     */
    private function update(Mailbox $mailbox, Request $request): array|RedirectResponse
    {
        $provider = $this->container->get(SystemConfigurationFormProvider::class);
        [$activeGroup, $activeSubGroup] = $provider->chooseActiveGroups(self::ACTIVE_GROUP, self::ACTIVE_SUBGROUP);
        $jsTree = $provider->getJsTree();
        $handler = $this->container->get(MailboxHandler::class);

        if ($handler->process($mailbox)) {
            $request->getSession()->getFlashBag()->add(
                'success',
                $this->container->get(TranslatorInterface::class)->trans(
                    'oro.email.mailbox.action.saved',
                    ['%mailbox%' => $mailbox->getLabel()]
                )
            );

            return $this->container->get(Router::class)->redirect([
                'route' => 'oro_email_mailbox_update',
                'id'    => $mailbox->getId()
            ]);
        }

        return [
            'data'           => $jsTree,
            'form'           => $handler->getForm()->createView(),
            'activeGroup'    => $activeGroup,
            'activeSubGroup' => $activeSubGroup,
            'redirectData'   => $this->getRedirectData($request),
        ];
    }

    #[Route(path: '/mailbox/create', name: 'oro_email_mailbox_create')]
    #[Template('@OroEmail/Configuration/Mailbox/update.html.twig')]
    public function createAction(Request $request): array|RedirectResponse
    {
        return $this->update(new Mailbox(), $request);
    }

    #[Route(path: '/mailbox/delete/{id}', name: 'oro_email_mailbox_delete', methods: ['DELETE'])]
    public function deleteAction(
        #[MapEntity]
        Mailbox $mailbox
    ): Response {
        $mailboxManager = $this->container->get('doctrine')->getManagerForClass(Mailbox::class);
        $mailboxManager->remove($mailbox);
        $mailboxManager->flush();

        return new Response(Response::HTTP_OK);
    }

    /**
     * This is a separate route for user searing within mailbox organization.
     */
    #[Route(path: '/mailbox/users/search/{organizationId}', name: 'oro_email_mailbox_users_search')]
    public function searchUsersAction(Request $request, int $organizationId): JsonResponse
    {
        $autocompleteRequest = new AutocompleteRequest($request);
        $result = [
            'results' => [],
            'hasMore' => false,
            'errors'  => []
        ];

        $violations = $this->container->get(ValidatorInterface::class)->validate($autocompleteRequest);
        foreach ($violations as $violation) {
            $result['errors'][] = $violation->getMessage();
        }

        if (!$this->container->get(Security::class)->isAutocompleteGranted($autocompleteRequest->getName())) {
            $result['errors'][] = 'Access denied.';
        }

        if (!empty($result['errors'])) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse($result);
            }

            throw new HttpException(Response::HTTP_OK, implode(', ', $result['errors']));
        }

        $searchHandler = $this->container->get(MailboxUserSearchHandler::class);
        $searchHandler->setOrganizationId($organizationId);

        return new JsonResponse(
            $searchHandler->search(
                $autocompleteRequest->getQuery(),
                $autocompleteRequest->getPage(),
                $autocompleteRequest->getPerPage(),
                $autocompleteRequest->isSearchById()
            )
        );
    }

    protected function getRedirectData(Request $request): array
    {
        try {
            return $request->query->all('redirectData');
        } catch (BadRequestException $e) {
            return
                [
                    'route' => 'oro_config_configuration_system',
                    'parameters' => [
                        'activeGroup' => self::ACTIVE_GROUP,
                        'activeSubGroup' => self::ACTIVE_SUBGROUP,
                    ]
                ];
        }
    }

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                TranslatorInterface::class,
                Router::class,
                ValidatorInterface::class,
                Security::class,
                MailboxUserSearchHandler::class,
                MailboxHandler::class,
                SystemConfigurationFormProvider::class,
                'doctrine' => ManagerRegistry::class,
            ]
        );
    }
}
