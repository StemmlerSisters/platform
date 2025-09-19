<?php

namespace Oro\Bundle\TranslationBundle\Controller;

use Oro\Bundle\DataGridBundle\Exception\LogicException;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionDispatcher;
use Oro\Bundle\SecurityBundle\Attribute\AclAncestor;
use Oro\Bundle\SecurityBundle\Attribute\CsrfProtection;
use Oro\Bundle\TranslationBundle\Entity\Translation;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translation Controller
 */
class TranslationController extends BaseController
{
    /**
     *
     * @return array
     */
    #[Route(path: '/', name: 'oro_translation_translation_index')]
    #[Template('@OroTranslation/Translation/index.html.twig')]
    #[AclAncestor('oro_translation_language_view')]
    public function indexAction()
    {
        return [
            'entity_class' => Translation::class
        ];
    }

    /**
     *
     * @param string $gridName
     * @param string $actionName
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(path: '/{gridName}/massAction/{actionName}', name: 'oro_translation_mass_reset')]
    #[AclAncestor('oro_translation_language_translate')]
    #[CsrfProtection()]
    public function resetMassAction($gridName, $actionName, Request $request)
    {
        $massActionDispatcher = $this->container->get(MassActionDispatcher::class);

        try {
            $response = $massActionDispatcher->dispatchByRequest($gridName, $actionName, $request);
            $data = array_merge(
                ['successful' => $response->isSuccessful(), 'message' => $response->getMessage()],
                $response->getOptions()
            );
        } catch (LogicException $e) {
            $translator = $this->container->get(TranslatorInterface::class);
            $data = [
                'successful' => false,
                'message' => $translator->trans('oro.translation.action.reset.nothing_to_reset'),
            ];
        }

        return new JsonResponse($data);
    }

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                TranslatorInterface::class,
                MassActionDispatcher::class,
            ]
        );
    }
}
