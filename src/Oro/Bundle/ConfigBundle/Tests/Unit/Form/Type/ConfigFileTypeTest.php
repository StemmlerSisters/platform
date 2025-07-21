<?php

namespace Oro\Bundle\ConfigBundle\Tests\Unit\Form\Type;

use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Form\Type\FileType;
use Oro\Bundle\AttachmentBundle\Tools\ExternalFileFactory;
use Oro\Bundle\ConfigBundle\Form\DataTransformer\ConfigFileDataTransformer;
use Oro\Bundle\ConfigBundle\Form\Type\ConfigFileType;
use Oro\Component\Testing\Unit\PreloadedExtension;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\HttpFoundation\File\File as HttpFile;
use Symfony\Component\Validator\Validation;

class ConfigFileTypeTest extends FormIntegrationTestCase
{
    private const FILE1_ID = 1;

    private ConfigFileDataTransformer&MockObject $transformer;
    private ConfigFileType $formType;

    #[\Override]
    protected function setUp(): void
    {
        $this->transformer = $this->createMock(ConfigFileDataTransformer::class);
        $this->formType = new ConfigFileType($this->transformer);
        parent::setUp();
    }

    public function testGetParent()
    {
        self::assertEquals(FileType::class, $this->formType->getParent());
    }

    public function testSubmitNull()
    {
        $this->transformer->expects(self::once())
            ->method('setFileConstraints')
            ->with(self::isType(IsType::TYPE_ARRAY));

        $file = new File();
        $this->transformer->expects(self::once())
            ->method('transform')
            ->with(null)
            ->willReturn($file);

        $this->transformer->expects(self::once())
            ->method('reverseTransform')
            ->with($file)
            ->willReturn(null);

        $form = $this->factory->create(ConfigFileType::class);
        $form->submit(null);

        self::assertNull($form->getData());
    }

    public function testSubmitFile()
    {
        $file = new File();

        $this->transformer->expects(self::once())
            ->method('setFileConstraints')
            ->with(self::isType(IsType::TYPE_ARRAY));

        $this->transformer->expects(self::once())
            ->method('transform')
            ->with(self::FILE1_ID)
            ->willReturn($file);

        $httpFile = new HttpFile('test.php', false);
        $this->transformer->expects(self::once())
            ->method('reverseTransform')
            ->with($file)
            ->willReturn(self::FILE1_ID);

        $form = $this->factory->create(ConfigFileType::class, self::FILE1_ID);
        $form->submit(['file' => $httpFile]);

        self::assertEquals(self::FILE1_ID, $form->getData());
    }

    #[\Override]
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([
                $this->formType,
                new FileType($this->createMock(ExternalFileFactory::class))
            ], []),
            new ValidatorExtension(Validation::createValidator())
        ];
    }
}
