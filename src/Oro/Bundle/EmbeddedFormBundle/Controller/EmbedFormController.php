<?php

namespace Oro\Bundle\EmbeddedFormBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EmbeddedFormBundle\Entity\EmbeddedForm;
use Oro\Bundle\EmbeddedFormBundle\Event\EmbeddedFormSubmitAfterEvent;
use Oro\Bundle\EmbeddedFormBundle\Event\EmbeddedFormSubmitBeforeEvent;
use Oro\Bundle\EmbeddedFormBundle\Manager\EmbeddedFormManager;
use Oro\Bundle\EmbeddedFormBundle\Manager\EmbedFormLayoutManager;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\PropertyAccess;
use Oro\Bundle\FormBundle\Form\Handler\RequestHandlerTrait;
use Oro\Bundle\OrganizationBundle\Form\Type\OwnershipType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles a form represents an embed entity.
 */
class EmbedFormController extends AbstractController
{
    use RequestHandlerTrait;

    /**
     *
     * @param EmbeddedForm $formEntity
     * @param Request $request
     * @return Response
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    #[Route(path: '/submit/{id}', name: 'oro_embedded_form_submit', requirements: ['id' => '[-\d\w]+'])]
    public function formAction(EmbeddedForm $formEntity, Request $request)
    {
        $response = new Response();
        $response->setPublic();
        $response->setEtag($formEntity->getId() . $formEntity->getUpdatedAt()->format(\DateTime::ISO8601));
        $this->setCorsHeaders($formEntity, $request, $response);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $isInline = $request->query->getBoolean('inline');

        $formManager = $this->container->get(EmbeddedFormManager::class);
        $form = $formManager->createForm($formEntity->getFormType());

        if (in_array($request->getMethod(), ['POST', 'PUT'])) {
            $dataClass = $form->getConfig()->getOption('data_class');
            if (isset($dataClass) && class_exists($dataClass)) {
                $ref         = new \ReflectionClass($dataClass);
                $constructor = $ref->getConstructor();
                $data        = $constructor && $constructor->getNumberOfRequiredParameters()
                    ? $ref->newInstanceWithoutConstructor()
                    : $ref->newInstance();

                $form->setData($data);
            } else {
                $data = [];
            }
            $event = new EmbeddedFormSubmitBeforeEvent($data, $formEntity);
            $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
            $eventDispatcher->dispatch($event, EmbeddedFormSubmitBeforeEvent::EVENT_NAME);
            $this->submitPostPutRequest($form, $request);

            $event = new EmbeddedFormSubmitAfterEvent($data, $formEntity, $form);
            $eventDispatcher->dispatch($event, EmbeddedFormSubmitAfterEvent::EVENT_NAME);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entity = $form->getData();

            /**
             * Set owner ID (current organization) to concrete form entity
             */
            $configProvider = $this->container->get(ConfigProvider::class);
            $entityConfig = $configProvider->getConfig(get_class($entity));
            $formEntityConfig = $configProvider->getConfig(get_class($formEntity));

            if ($entityConfig->get('owner_type') === OwnershipType::OWNER_TYPE_ORGANIZATION) {
                $accessor = PropertyAccess::createPropertyAccessor();
                $accessor->setValue(
                    $entity,
                    $entityConfig->get('owner_field_name'),
                    $accessor->getValue($formEntity, $formEntityConfig->get('owner_field_name'))
                );
            }
            $em = $this->container->get(ManagerRegistry::class)->getManagerForClass(get_class($entity));
            $em->persist($entity);
            $em->flush();

            $redirectUrl = $this->generateUrl('oro_embedded_form_success', [
                'id' => $formEntity->getId(),
                'inline' => $isInline
            ]);

            $redirectResponse = new RedirectResponse($redirectUrl);
            $this->setCorsHeaders($formEntity, $request, $redirectResponse);

            return $redirectResponse;
        }

        $layoutManager = $this->container->get(EmbedFormLayoutManager::class);

        $layoutManager->setInline($isInline);

        $response->setContent($layoutManager->getLayout($formEntity, $form)->render());

        return $response;
    }

    /**
     *
     * @param EmbeddedForm $formEntity
     * @param Request $request
     * @return Response
     */
    #[Route(path: '/success/{id}', name: 'oro_embedded_form_success', requirements: ['id' => '[-\d\w]+'])]
    public function formSuccessAction(EmbeddedForm $formEntity, Request $request)
    {
        $response = new Response();
        $response->setPublic();
        $response->setEtag(
            $formEntity->getId() . $formEntity->getUpdatedAt()->format(\DateTime::ISO8601)
        );
        $this->setCorsHeaders($formEntity, $request, $response);

        $layoutManager = $this->container->get(EmbedFormLayoutManager::class);

        $layoutManager->setInline($request->query->getBoolean('inline'));

        $response->setContent($layoutManager->getLayout($formEntity)->render());

        return $response;
    }

    /**
     * Checks if Origin request header match any of the allowed domains
     * and set Access-Control-Allow-Origin
     */
    protected function setCorsHeaders(EmbeddedForm $formEntity, Request $request, Response $response)
    {
        // skip if not a CORS request
        if (!$request->headers->has('Origin')
            || $request->headers->get('Origin') == $request->getSchemeAndHttpHost()
        ) {
            return;
        }

        // skip if no allowed domains
        $allowedDomains = $formEntity->getAllowedDomains();
        if (empty($allowedDomains)) {
            return;
        }

        $allowedDomains = explode("\n", $allowedDomains);
        $origin = $request->headers->get('Origin');

        foreach ($allowedDomains as $allowedDomain) {
            $regexp = '#^https?:\/\/' . str_replace('\*', '.*', preg_quote($allowedDomain, '#')) . '$#i';
            if ('*' === $allowedDomain || preg_match($regexp, $origin)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Allow-Headers', 'Accept-Encoding');

                break;
            }
        }
    }

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                EmbeddedFormManager::class,
                EventDispatcherInterface::class,
                EmbedFormLayoutManager::class,
                ConfigProvider::class,
                ManagerRegistry::class,
            ]
        );
    }
}
