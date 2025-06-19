<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Provider;

use Oro\Bundle\AttachmentBundle\DependencyInjection\Configuration;
use Oro\Bundle\AttachmentBundle\Helper\FieldConfigHelper;
use Oro\Bundle\AttachmentBundle\Provider\AttachmentEntityConfigProviderInterface;
use Oro\Bundle\AttachmentBundle\Provider\FileConstraintsProvider;
use Oro\Bundle\ConfigBundle\Config\ConfigManager as SystemConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class FileConstraintsProviderTest extends TestCase
{
    private SystemConfigManager&MockObject $systemConfigManager;
    private AttachmentEntityConfigProviderInterface&MockObject $attachmentEntityConfigProvider;
    private FileConstraintsProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->systemConfigManager = $this->createMock(SystemConfigManager::class);
        $this->attachmentEntityConfigProvider = $this->createMock(AttachmentEntityConfigProviderInterface::class);

        $this->provider = new FileConstraintsProvider(
            $this->systemConfigManager,
            $this->attachmentEntityConfigProvider
        );
    }

    public function testGetFileMimeTypes(): void
    {
        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.upload_file_mime_types', false, false, null)
            ->willReturn('sample/type1,sample/type2');

        $this->assertEquals(['sample/type1', 'sample/type2'], $this->provider->getFileMimeTypes());
    }

    public function testGetImageMimeTypes(): void
    {
        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.upload_image_mime_types', false, false, null)
            ->willReturn('sample/type1,sample/type2');

        $this->assertEquals(['sample/type1', 'sample/type2'], $this->provider->getImageMimeTypes());
    }

    /**
     * @dataProvider mimeTypesDataProvider
     */
    public function testGetMimeTypes(?string $fileMimeTypes, ?string $imageMimeTypes, array $expected): void
    {
        $this->systemConfigManager->expects($this->any())
            ->method('get')
            ->willReturnMap([
                ['oro_attachment.upload_file_mime_types', false, false, null, $fileMimeTypes],
                ['oro_attachment.upload_image_mime_types', false, false, null, $imageMimeTypes],
            ]);

        $this->assertEquals($expected, $this->provider->getMimeTypes());
    }

    public function mimeTypesDataProvider(): array
    {
        return [
            [null, null, []],
            [
                'sample/type1',
                'sample/type2',
                ['sample/type1', 'sample/type2'],
            ],
            [
                'sample/type1',
                '',
                ['sample/type1'],
            ],
            [
                '',
                'sample/type2',
                ['sample/type2'],
            ],
            [
                'sample/type1,sample/type2',
                'sample/type2',
                ['sample/type1', 'sample/type2'],
            ],
        ];
    }

    /**
     * @dataProvider mimeTypesAsChoicesDataProvider
     */
    public function testGetMimeTypesAsChoices(?string $fileMimeTypes, ?string $imageMimeTypes, array $expected): void
    {
        $this->systemConfigManager->expects($this->any())
            ->method('get')
            ->willReturnMap([
                ['oro_attachment.upload_file_mime_types', false, false, null, $fileMimeTypes],
                ['oro_attachment.upload_image_mime_types', false, false, null, $imageMimeTypes],
            ]);

        $this->assertEquals($expected, $this->provider->getMimeTypesAsChoices());
    }

    public function mimeTypesAsChoicesDataProvider(): array
    {
        return [
            [null, null, []],
            [
                'sample/type1',
                'sample/type2',
                ['sample/type1' => 'sample/type1', 'sample/type2' => 'sample/type2'],
            ],
            [
                'sample/type1',
                '',
                ['sample/type1' => 'sample/type1'],
            ],
            [
                '',
                'sample/type2',
                ['sample/type2' => 'sample/type2'],
            ],
            [
                'sample/type1,sample/type2',
                'sample/type2',
                ['sample/type1' => 'sample/type1', 'sample/type2' => 'sample/type2'],
            ],
        ];
    }

    public function testGetAllowedMimeTypesForEntityWhenNoFieldConfig(): void
    {
        $entityClass = \stdClass::class;

        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getEntityConfig')
            ->with($entityClass)
            ->willReturn(null);

        $this->systemConfigManager->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['oro_attachment.upload_file_mime_types', false, false, null, 'sample/type1'],
                ['oro_attachment.upload_image_mime_types', false, false, null, 'sample/type2'],
            ]);

        $this->assertEquals(
            ['sample/type1', 'sample/type2'],
            $this->provider->getAllowedMimeTypesForEntity($entityClass)
        );
    }

    public function testGetAllowedMimeTypesForEntityWhenNoMimeTypes(): void
    {
        $entityClass = \stdClass::class;

        $entityConfig = $this->createMock(ConfigInterface::class);
        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getEntityConfig')
            ->with($entityClass)
            ->willReturn($entityConfig);
        $entityConfig->expects($this->once())
            ->method('get')
            ->with('mimetypes')
            ->willReturn('');

        $this->systemConfigManager->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['oro_attachment.upload_file_mime_types', false, false, null, 'sample/type1'],
                ['oro_attachment.upload_image_mime_types', false, false, null, 'sample/type2'],
            ]);

        $this->assertEquals(
            ['sample/type1', 'sample/type2'],
            $this->provider->getAllowedMimeTypesForEntity($entityClass)
        );
    }

    public function testGetAllowedMimeTypesForEntity(): void
    {
        $entityClass = \stdClass::class;

        $entityConfig = $this->createMock(ConfigInterface::class);
        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getEntityConfig')
            ->with($entityClass)
            ->willReturn($entityConfig);
        $entityConfig->expects($this->once())
            ->method('get')
            ->with('mimetypes')
            ->willReturn('sample/type1,sample/type2');

        $this->systemConfigManager->expects($this->never())
            ->method('get');

        $this->assertEquals(
            ['sample/type1', 'sample/type2'],
            $this->provider->getAllowedMimeTypesForEntity($entityClass)
        );
    }

    public function testGetAllowedMimeTypesForEntityFieldWhenNoFieldConfig(): void
    {
        $entityClass = \stdClass::class;
        $fieldName = 'sampleField';

        $this->attachmentEntityConfigProvider->expects($this->once())
            ->method('getFieldConfig')
            ->with($entityClass, $fieldName)
            ->willReturn(null);

        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.upload_file_mime_types', false, false, null)
            ->willReturn('sample/type1,sample/type2');

        $this->assertEquals(
            ['sample/type1', 'sample/type2'],
            $this->provider->getAllowedMimeTypesForEntityField($entityClass, $fieldName)
        );
    }

    /**
     * @dataProvider getAllowedMimeTypesForEntityFieldWhenImageAndNoMimeTypesProvider
     */
    public function testGetAllowedMimeTypesForEntityFieldWhenImageAndNoMimeTypes(
        string $inputType,
        array $expectedResult
    ): void {
        $entityClass = \stdClass::class;
        $fieldName = 'sampleField';

        $entityFieldConfig = $this->createMock(ConfigInterface::class);
        $fieldConfigId = $this->createMock(FieldConfigId::class);
        $this->attachmentEntityConfigProvider->expects($this->once())
            ->method('getFieldConfig')
            ->with($entityClass, $fieldName)
            ->willReturn($entityFieldConfig);
        $entityFieldConfig->expects($this->once())
            ->method('get')
            ->with('mimetypes')
            ->willReturn('');
        $entityFieldConfig->expects($this->once())
            ->method('getId')
            ->willReturn($fieldConfigId);
        $fieldConfigId->expects($this->once())
            ->method('getFieldType')
            ->willReturn($inputType);

        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->willReturnMap([
                ['oro_attachment.upload_image_mime_types', false, false, null, 'image/type1,image/type2'],
                ['oro_attachment.upload_file_mime_types', false, false, null, 'file/type1,file/type2'],
            ]);

        $this->assertEquals(
            $expectedResult,
            $this->provider->getAllowedMimeTypesForEntityField($entityClass, $fieldName)
        );
    }

    public function getAllowedMimeTypesForEntityFieldWhenImageAndNoMimeTypesProvider(): array
    {
        return [
            'image' => [
                'input' => FieldConfigHelper::IMAGE_TYPE,
                'expected' => ['image/type1', 'image/type2'],
            ],
            'multiImage' => [
                'input' => FieldConfigHelper::MULTI_IMAGE_TYPE,
                'expected' => ['image/type1', 'image/type2'],
            ],
            'other' => [
                'input' => 'other',
                'expected' => ['file/type1', 'file/type2'],
            ],
        ];
    }

    public function testGetAllowedMimeTypesForEntityFieldWhenNotImageAndNoMimeTypes(): void
    {
        $entityClass = \stdClass::class;
        $fieldName = 'sampleField';

        $entityFieldConfig = $this->createMock(ConfigInterface::class);
        $fieldConfigId = $this->createMock(FieldConfigId::class);
        $this->attachmentEntityConfigProvider->expects($this->once())
            ->method('getFieldConfig')
            ->with($entityClass, $fieldName)
            ->willReturn($entityFieldConfig);
        $entityFieldConfig->expects($this->once())
            ->method('get')
            ->with('mimetypes')
            ->willReturn('');
        $entityFieldConfig->expects($this->once())
            ->method('getId')
            ->willReturn($fieldConfigId);
        $fieldConfigId->expects($this->once())
            ->method('getFieldType')
            ->willReturn('another_type');

        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.upload_file_mime_types', false, false, null)
            ->willReturn('sample/type1,sample/type2');

        $this->assertEquals(
            ['sample/type1', 'sample/type2'],
            $this->provider->getAllowedMimeTypesForEntityField($entityClass, $fieldName)
        );
    }

    public function testGetAllowedMimeTypesForEntityField(): void
    {
        $entityClass = \stdClass::class;
        $fieldName = 'sampleField';

        $entityFieldConfig = $this->createMock(ConfigInterface::class);
        $this->attachmentEntityConfigProvider->expects($this->once())
            ->method('getFieldConfig')
            ->with($entityClass, $fieldName)
            ->willReturn($entityFieldConfig);
        $entityFieldConfig->expects($this->once())
            ->method('get')
            ->with('mimetypes')
            ->willReturn('sample/type1,sample/type2');
        $entityFieldConfig->expects($this->never())
            ->method('getId');

        $this->assertEquals(
            ['sample/type1', 'sample/type2'],
            $this->provider->getAllowedMimeTypesForEntityField($entityClass, $fieldName)
        );
    }

    public function testGetMaxSize(): void
    {
        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.maxsize', false, false, null)
            ->willReturn(10);

        $this->assertEquals(10 * Configuration::BYTES_MULTIPLIER, $this->provider->getMaxSize());
    }

    public function testGetMaxSizeByConfigPath(): void
    {
        $key = 'oro_attachment.maxsize_key';
        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with($key, false, false, null)
            ->willReturn(0.03);

        $this->assertEquals(
            (int)(0.03 * Configuration::BYTES_MULTIPLIER),
            $this->provider->getMaxSizeByConfigPath($key)
        );
    }

    public function testGetMaxSizeForEntityWhenNoEntityConfig(): void
    {
        $entityClass = \stdClass::class;

        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getEntityConfig')
            ->with($entityClass)
            ->willReturn(null);

        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.maxsize', false, false, null)
            ->willReturn(10);

        $this->assertEquals(
            10 * Configuration::BYTES_MULTIPLIER,
            $this->provider->getMaxSizeForEntity($entityClass)
        );
    }

    public function testGetMaxSizeForEntityWhenNoMaxSize(): void
    {
        $entityClass = \stdClass::class;

        $entityConfig = $this->createMock(ConfigInterface::class);
        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getEntityConfig')
            ->with($entityClass)
            ->willReturn($entityConfig);
        $entityConfig->expects($this->once())
            ->method('get')
            ->with('maxsize')
            ->willReturn(null);

        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.maxsize', false, false, null)
            ->willReturn(10);

        $this->assertEquals(
            10 * Configuration::BYTES_MULTIPLIER,
            $this->provider->getMaxSizeForEntity($entityClass)
        );
    }

    public function testGetMaxSizeForEntity(): void
    {
        $entityClass = \stdClass::class;

        $entityConfig = $this->createMock(ConfigInterface::class);
        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getEntityConfig')
            ->with($entityClass)
            ->willReturn($entityConfig);
        $entityConfig->expects($this->once())
            ->method('get')
            ->with('maxsize')
            ->willReturn(10);

        $this->systemConfigManager->expects($this->never())
            ->method('get');

        $this->assertEquals(
            10 * Configuration::BYTES_MULTIPLIER,
            $this->provider->getMaxSizeForEntity($entityClass)
        );
    }

    public function testGetMaxSizeForEntityFieldWhenNoFieldConfig(): void
    {
        $entityClass = \stdClass::class;
        $fieldName = 'sampleField';

        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getFieldConfig')
            ->with($entityClass, $fieldName)
            ->willReturn(null);

        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.maxsize', false, false, null)
            ->willReturn(10);

        $this->assertEquals(
            10 * Configuration::BYTES_MULTIPLIER,
            $this->provider->getMaxSizeForEntityField($entityClass, $fieldName)
        );
    }

    public function testGetMaxSizeForEntityFieldWhenNoMaxSize(): void
    {
        $entityClass = \stdClass::class;
        $fieldName = 'sampleField';

        $entityFieldConfig = $this->createMock(ConfigInterface::class);
        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getFieldConfig')
            ->with($entityClass, $fieldName)
            ->willReturn($entityFieldConfig);
        $entityFieldConfig->expects($this->once())
            ->method('get')
            ->with('maxsize')
            ->willReturn(null);

        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.maxsize', false, false, null)
            ->willReturn(10);

        $this->assertEquals(
            10 * Configuration::BYTES_MULTIPLIER,
            $this->provider->getMaxSizeForEntityField($entityClass, $fieldName)
        );
    }

    public function testGetMaxSizeForEntityField(): void
    {
        $entityClass = \stdClass::class;
        $fieldName = 'sampleField';

        $entityFieldConfig = $this->createMock(ConfigInterface::class);
        $this->attachmentEntityConfigProvider->expects($this->any())
            ->method('getFieldConfig')
            ->with($entityClass, $fieldName)
            ->willReturn($entityFieldConfig);
        $entityFieldConfig->expects($this->once())
            ->method('get')
            ->with('maxsize')
            ->willReturn(10);

        $this->systemConfigManager->expects($this->never())
            ->method('get');

        $this->assertEquals(
            10 * Configuration::BYTES_MULTIPLIER,
            $this->provider->getMaxSizeForEntityField($entityClass, $fieldName)
        );
    }

    /**
     * @dataProvider getExternalFileAllowedUrlsRegExpDataProvider
     */
    public function testGetExternalFileAllowedUrlsRegExp(mixed $value, string $expectedValue): void
    {
        $this->systemConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_attachment.external_file_allowed_urls_regexp', false, false, null)
            ->willReturn($value);

        self::assertSame($expectedValue, $this->provider->getExternalFileAllowedUrlsRegExp());
    }

    public function getExternalFileAllowedUrlsRegExpDataProvider(): array
    {
        return [
            [null, ''],
            ['', ''],
            [123, '~123~i'],
            ['sample value', '~sample value~i'],
        ];
    }
}
