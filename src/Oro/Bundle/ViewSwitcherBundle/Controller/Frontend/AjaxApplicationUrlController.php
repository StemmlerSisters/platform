<?php

namespace Oro\Bundle\ViewSwitcherBundle\Controller\Frontend;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * This controller provides access to application_url config value for the view switcher js part.
 */
class AjaxApplicationUrlController extends AbstractController
{
    /**
     * @return JsonResponse
     */
    #[Route(path: '/view-switcher/get-application-url', name: 'oro_view_switcher_frontend_get_application_url')]
    public function getApplicationUrl()
    {
        $applicationUrl = $this->getConfigManager()->get('oro_ui.application_url');

        return new JsonResponse([
            'applicationUrl' => $applicationUrl,
        ]);
    }

    /**
     * @return ConfigManager
     */
    protected function getConfigManager()
    {
        return $this->container->get(ConfigManager::class);
    }

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return [
            ConfigManager::class,
        ];
    }
}
