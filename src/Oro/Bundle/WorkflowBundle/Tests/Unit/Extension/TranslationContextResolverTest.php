<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Extension;

use Oro\Bundle\WorkflowBundle\Extension\TranslationContextResolver;
use Oro\Bundle\WorkflowBundle\Translation\KeyTemplate\WorkflowTemplate;
use Oro\Bundle\WorkflowBundle\Translation\KeyTemplateParametersResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslationContextResolverTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;
    private KeyTemplateParametersResolver&MockObject $resolver;
    private TranslationContextResolver $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->resolver = $this->createMock(KeyTemplateParametersResolver::class);

        $this->extension = new TranslationContextResolver($this->translator, $this->resolver);
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(string $inputKey, string $resolvedId, array $resolvedParameters): void
    {
        $this->resolver->expects($this->once())
            ->method('resolveTemplateParameters')
            ->with($resolvedParameters)
            ->willReturn(['argument1' => 'value1']);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with($resolvedId, ['argument1' => 'value1'])
            ->willReturn('translatedString');

        $this->assertEquals('translatedString', $this->extension->resolve($inputKey));
    }

    public function resolveProvider(): array
    {
        $keyPrefix = WorkflowTemplate::KEY_PREFIX;
        $templatePrefix = str_replace('{{ template }}', '', TranslationContextResolver::TRANSLATION_TEMPLATE);

        return [
            'workflow_label' => [
                'input' => $keyPrefix . '.workflow1.label',
                'resolvedId' => $templatePrefix . 'workflow_label',
                'resolvedParams' => ['workflow_name' => 'workflow1'],
            ],
            'transition_label' => [
                'input' => $keyPrefix . '.workflow1.transition.transition1.label',
                'resolvedId' => $templatePrefix . 'transition_label',
                'resolvedParams' => ['workflow_name' => 'workflow1', 'transition_name' => 'transition1'],
            ],
            'transition_warning_message' => [
                'input' => $keyPrefix . '.workflow1.transition.transition1.warning_message',
                'resolvedId' => $templatePrefix . 'transition_warning_message',
                'resolvedParams' => ['workflow_name' => 'workflow1', 'transition_name' => 'transition1'],
            ],
            'transition_attribute_label' => [
                'input' => $keyPrefix . '.workflow1.transition.transition1.attribute.attribute1.label',
                'resolvedId' => $templatePrefix . 'transition_attribute_label',
                'resolvedParams' => [
                    'workflow_name' => 'workflow1',
                    'transition_name' => 'transition1',
                    'attribute_name' => 'attribute1',
                ],
            ],
            'step_label' => [
                'input' => $keyPrefix . '.workflow1.step.step1.label',
                'resolvedId' => $templatePrefix . 'step_label',
                'resolvedParams' => ['workflow_name' => 'workflow1', 'step_name' => 'step1'],
            ],
            'workflow_attribute_label' => [
                'input' => $keyPrefix . '.workflow1.attribute.attribute1.label',
                'resolvedId' => $templatePrefix . 'workflow_attribute_label',
                'resolvedParams' => ['workflow_name' => 'workflow1', 'attribute_name' => 'attribute1'],
            ],
        ];
    }

    /**
     * @dataProvider resolveUnresolvedKeysProvider
     */
    public function testResolveUnresolvedKeys(string $input): void
    {
        $this->translator->expects($this->never())
            ->method('trans');

        $this->assertNull($this->extension->resolve($input));
    }

    public function resolveUnresolvedKeysProvider(): array
    {
        return [
            'not applicable key' => [
                'input' => 'not_applicable_key',
            ],
            'unknown root key' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.unknown_key',
            ],
            'unknown workflow key' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.unknown_key',
            ],
            'unknown transition key 1' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.transition',
            ],
            'unknown transition key 2' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.transition.transition1',
            ],
            'unknown transition key 3' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.transition.transition1.unknown_key',
            ],
            'unknown transition attribute key 1' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.transition.transition1.attribute',
            ],
            'unknown transition attribute key 2' => [
                'input' => WorkflowTemplate::KEY_PREFIX .
                    '.workflow1.transition.transition1.attribute.attribute1.unknown_key',
            ],
            'unknown step key' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.step',
            ],
            'unknown step key 2' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.step.step1',
            ],
            'unknown step key 3' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.step.step1.unknown_key',
            ],
            'unknown attribute key' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.attribute',
            ],
            'unknown attribute key 2' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.attribute.attribute1',
            ],
            'unknown attribute key 3' => [
                'input' => WorkflowTemplate::KEY_PREFIX . '.workflow1.attribute.attribute1.unknown_key',
            ],
        ];
    }
}
