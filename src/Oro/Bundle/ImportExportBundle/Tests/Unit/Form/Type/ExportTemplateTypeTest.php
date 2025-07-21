<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Form\Type;

use Oro\Bundle\ImportExportBundle\Form\Model\ExportData;
use Oro\Bundle\ImportExportBundle\Form\Type\ExportTemplateType;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorRegistry;
use Oro\Component\Testing\Unit\PreloadedExtension;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Test\FormIntegrationTestCase;

class ExportTemplateTypeTest extends FormIntegrationTestCase
{
    private ProcessorRegistry&MockObject $processorRegistry;
    private ExportTemplateType $exportTemplateType;

    #[\Override]
    protected function setUp(): void
    {
        $this->processorRegistry = $this->createMock(ProcessorRegistry::class);
        $this->exportTemplateType = new ExportTemplateType($this->processorRegistry);
        parent::setUp();
    }

    #[\Override]
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([$this->exportTemplateType], [])
        ];
    }

    public function testSubmit()
    {
        $entityName = 'TestEntity';
        $processorAliases = [
            'first_alias',
            'second_alias'
        ];
        $expectedChoices = [
            new ChoiceView('first_alias', 'first_alias', 'oro.importexport.export_template.first_alias'),
            new ChoiceView('second_alias', 'second_alias', 'oro.importexport.export_template.second_alias')
        ];
        $usedAlias = 'second_alias';

        $this->processorRegistry->expects($this->any())
            ->method('getProcessorAliasesByEntity')
            ->with(ProcessorRegistry::TYPE_EXPORT_TEMPLATE, $entityName)
            ->willReturn($processorAliases);

        $form = $this->factory->create(ExportTemplateType::class, null, ['entityName' => $entityName]);

        $processorAliasConfig = $form->get('processorAlias')->getConfig();
        $this->assertEquals('oro.importexport.export.popup.options.label', $processorAliasConfig->getOption('label'));

        $this->assertEquals(
            $expectedChoices,
            $form->get('processorAlias')->createView()->vars['choices']
        );

        $this->assertTrue($processorAliasConfig->getOption('required'));
        $this->assertNull($processorAliasConfig->getOption('placeholder'));

        $form->submit(['processorAlias' => $usedAlias]);
        $this->assertTrue($form->isValid());
        $this->assertTrue($form->isSynchronized());

        /** @var ExportData $data */
        $data = $form->getData();
        $this->assertInstanceOf(ExportData::class, $data);
        $this->assertEquals($usedAlias, $data->getProcessorAlias());
    }
}
