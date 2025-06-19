<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Formatter;

use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Formatter\ImageSrcFormatter;
use Oro\Bundle\AttachmentBundle\Manager\AttachmentManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImageSrcFormatterTest extends TestCase
{
    private AttachmentManager&MockObject $manager;
    private ImageSrcFormatter $formatter;

    #[\Override]
    protected function setUp(): void
    {
        $this->manager = $this->createMock(AttachmentManager::class);

        $this->formatter = new ImageSrcFormatter($this->manager);
    }

    public function testFormat(): void
    {
        $file = new File();

        $this->manager->expects($this->once())
            ->method('getResizedImageUrl')
            ->with($file, 100, 100)
            ->willReturn('http://test.com/image.png');
        $this->assertEquals('http://test.com/image.png', $this->formatter->format($file));
    }

    public function testFormatWithArguments(): void
    {
        $file = new File();
        $file->setOriginalFilename('some_name.png');

        $width = 20;
        $height = 30;
        $title = 'test title';
        $format = 'sample-format';

        $this->manager->expects($this->once())
            ->method('getResizedImageUrl')
            ->with($file, $width, $height, $format)
            ->willReturn('http://test.com/image.png');
        $this->assertEquals(
            'http://test.com/image.png',
            $this->formatter->format(
                $file,
                [
                    'width' => $width,
                    'height' => $height,
                    'title' => $title,
                    'format' => $format,
                ]
            )
        );
    }

    public function testGetDefaultValue(): void
    {
        $this->assertEquals('#', $this->formatter->getDefaultValue());
    }
}
