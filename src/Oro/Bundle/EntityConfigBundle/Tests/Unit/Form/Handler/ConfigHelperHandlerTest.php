<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Form\Handler;

use Oro\Bundle\EntityConfigBundle\Config\ConfigHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Form\Handler\ConfigHelperHandler;
use Oro\Bundle\EntityConfigBundle\Form\Type\ConfigType;
use Oro\Bundle\EntityExtendBundle\Form\Type\FieldType;
use Oro\Bundle\UIBundle\Route\Router;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class ConfigHelperHandlerTest extends TestCase
{
    use EntityTrait;

    private FormFactory&MockObject $formFactory;
    private Session&MockObject $session;
    private Router&MockObject $router;
    private ConfigHelper&MockObject $configHelper;
    private FormInterface&MockObject $form;
    private Request $request;
    private ConfigHelperHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->formFactory = $this->createMock(FormFactory::class);
        $this->session = $this->createMock(Session::class);
        $this->router = $this->createMock(Router::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->form = $this->createMock(FormInterface::class);
        $this->request = $this->createMock(Request::class);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects(self::any())
            ->method('getSession')
            ->willReturn($this->session);

        $this->handler = new ConfigHelperHandler(
            $this->formFactory,
            $requestStack,
            $this->router,
            $this->configHelper,
        );
    }

    public function testCreateFirstStepFieldForm(): void
    {
        $entityClassName = 'someClassName';
        $entityConfigModel = $this->getEntity(EntityConfigModel::class, ['className' => $entityClassName]);
        $fieldConfigModel = $this->getEntity(FieldConfigModel::class, ['entity' => $entityConfigModel]);

        $this->formFactory->expects(self::once())
            ->method('create')
            ->with(
                FieldType::class,
                $fieldConfigModel,
                ['class_name' => $entityClassName]
            )
            ->willReturn($this->form);

        self::assertEquals($this->form, $this->handler->createFirstStepFieldForm($fieldConfigModel));
    }

    public function testCreateSecondStepFieldForm(): void
    {
        $fieldConfigModel = $this->getEntity(FieldConfigModel::class);

        $this->formFactory->expects(self::once())
            ->method('create')
            ->with(
                ConfigType::class,
                null,
                ['config_model' => $fieldConfigModel]
            )
            ->willReturn($this->form);

        self::assertEquals($this->form, $this->handler->createSecondStepFieldForm($fieldConfigModel));
    }

    public function testIsFormValidAfterSubmitWhenMethodIsNotPost(): void
    {
        $this->request->expects(self::once())
            ->method('isMethod')
            ->with('POST')
            ->willReturn(false);

        $this->form->expects(self::never())
            ->method('handleRequest');

        self::assertFalse($this->handler->isFormValidAfterSubmit($this->request, $this->form));
    }

    public function testIsFormValidAfterSubmitWhenMethodIsPostAndFormIsNotValid(): void
    {
        $formName = 'sample_form';
        $parameters = [$formName => ['sample_key' => 'sample_value']];
        $this->request = Request::create('/', Request::METHOD_POST, $parameters);

        $this->form->expects(self::atLeastOnce())
            ->method('getName')
            ->willReturn($formName);
        $this->form->expects(self::once())
            ->method('submit')
            ->with($parameters[$formName], true);
        $this->form->expects(self::once())
            ->method('isSubmitted')
            ->willReturn(true);
        $this->form->expects(self::once())
            ->method('isValid')
            ->willReturn(false);

        self::assertFalse($this->handler->isFormValidAfterSubmit($this->request, $this->form));
    }

    public function testIsFormValidAfterSubmitWhenMethodIsPostAndFormIsValid(): void
    {
        $formName = 'sample_form';
        $parameters = [$formName => ['sample_key' => 'sample_value']];
        $request = Request::create('/', Request::METHOD_POST, $parameters);

        $this->form->expects(self::atLeastOnce())
            ->method('getName')
            ->willReturn($formName);
        $this->form->expects(self::once())
            ->method('submit')
            ->with($parameters[$formName], true);
        $this->form->expects(self::once())
            ->method('isSubmitted')
            ->willReturn(true);
        $this->form->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        self::assertTrue($this->handler->isFormValidAfterSubmit($request, $this->form));
    }

    public function testIsFormValidAfterSubmitWhenMethodIsPostAndFormIsValidButPartial(): void
    {
        $formName = 'sample_form';
        $parameters = [$formName => ['sample_key' => 'sample_value', ConfigType::PARTIAL_SUBMIT => true]];
        $request = Request::create('/', Request::METHOD_POST, $parameters);

        $this->form->expects(self::atLeastOnce())
            ->method('getName')
            ->willReturn($formName);
        $this->form->expects(self::once())
            ->method('submit')
            ->with($parameters[$formName], false);
        $this->form->expects(self::once())
            ->method('isSubmitted')
            ->willReturn(true);
        $this->form->expects(self::once())
            ->method('isValid')
            ->willReturn(true);

        self::assertFalse($this->handler->isFormValidAfterSubmit($request, $this->form));
    }

    public function testShowSuccessMessageAndRedirect(): void
    {
        $successMessage = 'Success message';

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects(self::once())
            ->method('add')
            ->with('success', $successMessage);

        $this->session->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $fieldConfigModel = $this->getEntity(FieldConfigModel::class);
        $this->router->expects(self::once())
            ->method('redirect')
            ->with($fieldConfigModel);

        $this->handler->showSuccessMessageAndRedirect($fieldConfigModel, $successMessage);
    }

    public function testConstructConfigResponse(): void
    {
        $fieldConfigModel = $this->getEntity(FieldConfigModel::class);

        $formView = new FormView();
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())
            ->method('createView')
            ->willReturn($formView);

        $formAction = 'someAction';

        $entityConfig = $this->createMock(ConfigInterface::class);
        $this->configHelper->expects(self::once())
            ->method('getEntityConfigByField')
            ->with($fieldConfigModel, 'entity')
            ->willReturn($entityConfig);

        $fieldConfig = $this->createMock(ConfigInterface::class);
        $this->configHelper->expects(self::once())
            ->method('getFieldConfig')
            ->with($fieldConfigModel, 'entity')
            ->willReturn($fieldConfig);

        $modules = ['somemodule'];
        $this->configHelper->expects(self::once())
            ->method('getExtendJsModules')
            ->willReturn($modules);

        $nonExtendedEntities = ['first', 'second'];
        $this->configHelper->expects(self::once())
            ->method('getNonExtendedEntitiesClasses')
            ->willReturn($nonExtendedEntities);

        $expectedResponse = [
            'entity_config' => $entityConfig,
            'field_config' => $fieldConfig,
            'field' => $fieldConfigModel,
            'form' => $formView,
            'formAction' => $formAction,
            'jsmodules' => $modules,
            'non_extended_entities_classes' => $nonExtendedEntities,
        ];

        self::assertEquals(
            $expectedResponse,
            $this->handler->constructConfigResponse($fieldConfigModel, $form, $formAction)
        );
    }

    public function testRedirect(): void
    {
        $fieldConfigModel = $this->getEntity(FieldConfigModel::class);

        $redirectResponse = new RedirectResponse('some_url');
        $this->router->expects(self::once())
            ->method('redirect')
            ->with($fieldConfigModel)
            ->willReturn($redirectResponse);

        $response = $this->handler->redirect($fieldConfigModel);

        self::assertEquals($redirectResponse->getStatusCode(), $response->getStatusCode());
        self::assertEquals($redirectResponse->getTargetUrl(), $response->getTargetUrl());
    }
}
