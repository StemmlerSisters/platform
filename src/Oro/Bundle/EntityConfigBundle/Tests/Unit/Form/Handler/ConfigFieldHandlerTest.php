<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Form\Handler;

use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Form\Handler\ConfigFieldHandler;
use Oro\Bundle\EntityConfigBundle\Form\Handler\ConfigHelperHandler;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ConfigFieldHandlerTest extends TestCase
{
    use EntityTrait;

    private const SAMPLE_FORM_ACTION = '/entity_config/create';
    private const SAMPLE_SUCCESS_MESSAGE = 'Entity config was successfully saved';

    private ConfigHelperHandler&MockObject $configHelperHandler;
    private RequestStack&MockObject $requestStack;
    private FieldConfigModel $fieldConfigModel;
    private ConfigFieldHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->configHelperHandler = $this->createMock(ConfigHelperHandler::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->fieldConfigModel = $this->getEntity(FieldConfigModel::class, ['id' => 777]);

        $this->handler = new ConfigFieldHandler(
            $this->configHelperHandler,
            $this->requestStack
        );
    }

    private function expectsFormCreationSubmissionAndValidation(bool $isFormValid): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $this->configHelperHandler->expects($this->once())
            ->method('createSecondStepFieldForm')
            ->with($this->fieldConfigModel)
            ->willReturn($form);

        $request = new Request();
        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->configHelperHandler->expects($this->once())
            ->method('isFormValidAfterSubmit')
            ->with($request, $form)
            ->willReturn($isFormValid);

        return $form;
    }

    public function testHandleUpdateWhenFormIsValid(): void
    {
        $this->expectsFormCreationSubmissionAndValidation(true);
        $successMessage = 'Success message';

        $redirectResponse = new RedirectResponse('someurl');
        $this->configHelperHandler->expects($this->once())
            ->method('showSuccessMessageAndRedirect')
            ->with($this->fieldConfigModel, $successMessage)
            ->willReturn($redirectResponse);

        $formAction = 'formAction';
        $response = $this->handler->handleUpdate($this->fieldConfigModel, $formAction, $successMessage);

        $this->assertEquals($redirectResponse->getTargetUrl(), $response->getTargetUrl());
        $this->assertEquals($redirectResponse->getStatusCode(), $response->getStatusCode());
    }

    public function testHandleUpdateWhenFormIsNotValid(): void
    {
        $form = $this->expectsFormCreationSubmissionAndValidation(false);
        $successMessage = 'Success message';

        $arrayResponse = [
            'entity_config' => 'Entity config'
        ];

        $formAction = 'formAction';
        $this->configHelperHandler->expects($this->once())
            ->method('constructConfigResponse')
            ->with($this->fieldConfigModel, $form, $formAction)
            ->willReturn($arrayResponse);

        $this->assertEquals(
            $arrayResponse,
            $this->handler->handleUpdate($this->fieldConfigModel, $formAction, $successMessage)
        );
    }
}
