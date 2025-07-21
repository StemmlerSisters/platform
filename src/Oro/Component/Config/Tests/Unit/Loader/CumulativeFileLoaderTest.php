<?php

namespace Oro\Component\Config\Tests\Unit\Loader;

use Oro\Component\Config\CumulativeResource;
use Oro\Component\Config\CumulativeResourceInfo;
use Oro\Component\Config\Loader\CumulativeFileLoader;
use Oro\Component\Config\Loader\CumulativeResourceLoaderCollection;
use Oro\Component\Config\Tests\Unit\Fixtures\Bundle\TestBundle1\TestBundle1;
use Oro\Component\Testing\TempDirExtension;
use PHPUnit\Framework\TestCase;

class CumulativeFileLoaderTest extends TestCase
{
    use TempDirExtension;

    private string $bundleDir;

    #[\Override]
    protected function setUp(): void
    {
        $tmpDir = $this->copyToTempDir('test_data', realpath(__DIR__ . '/../Fixtures'));
        $this->bundleDir = $tmpDir . DIRECTORY_SEPARATOR . 'Bundle' . DIRECTORY_SEPARATOR . 'TestBundle1';
    }

    private function getPath(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function testLoader(): void
    {
        $relativeFilePath = 'Resources/config/test.yml';

        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);

        $data = ['test' => 123];
        $bundleDir = $this->bundleDir;
        $expectedFilePath = $bundleDir . '/' . $relativeFilePath;
        $expectedFilePath = $this->getPath($expectedFilePath);

        $expectedResource = new CumulativeResourceInfo(
            TestBundle1::class,
            'test',
            $expectedFilePath,
            $data
        );

        $loader->expects($this->once())
            ->method('loadFile')
            ->with($expectedFilePath)
            ->willReturn($data);

        $this->assertEquals($relativeFilePath, $loader->getResource());

        $resource = $loader->load(TestBundle1::class, $bundleDir);
        $this->assertEquals($expectedResource, $resource);
    }

    /**
     * @dataProvider filePathProvider
     */
    public function testFilePath(
        string $relativeFilePath,
        string $expectedRelativeFilePath,
        string $expectedResource
    ): void {
        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);

        $this->assertEquals($expectedRelativeFilePath, $loader->getRelativeFilePath());
        $this->assertEquals($expectedResource, $loader->getResource());
    }

    public function testRegisterFoundResource(): void
    {
        $relativeFilePath = 'Resources/config/test.yml';

        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $expectedResource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $expectedResource->addFound(
            $bundleClass,
            $this->getPath($bundleDir . '/' . $relativeFilePath)
        );
        $this->assertEquals($expectedResource, $resource);
    }

    public function testIsResourceFreshNoChanges(): void
    {
        $relativeFilePath = 'Resources/config/test.yml';

        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);

        $loadTime = filemtime($bundleDir . '/' . $relativeFilePath) + 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $this->assertTrue($loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
    }

    public function testIsResourceFreshNoFile(): void
    {
        $relativeFilePath = 'Resources/config/none.tmp';

        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);

        $loadTime = filemtime($bundleDir) + 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $this->assertTrue($loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
    }

    public function testIsResourceFreshExistingFileWasChanged(): void
    {
        $relativeFilePath = 'Resources/config/test.yml';

        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);

        $loadTime = filemtime($bundleDir . '/' . $relativeFilePath) - 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $this->assertFalse($loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
    }

    public function testIsResourceFreshNewFileWasAdded(): void
    {
        $relativeFilePath = 'Resources/config/test.tmp';

        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);

        $loadTime = filemtime($bundleDir) + 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $filePath = $bundleDir . '/' . $relativeFilePath;
        file_put_contents($filePath, 'test');
        $result = $loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime);
        unlink($filePath);
        $this->assertFalse($result);
    }

    public function testIsResourceFreshNewFileWasDeleted(): void
    {
        $relativeFilePath = 'Resources/config/test.tmp';

        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);

        $filePath = $bundleDir . '/' . $relativeFilePath;
        file_put_contents($filePath, 'test');
        $loadTime = filemtime($bundleDir . '/' . $relativeFilePath) + 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);
        unlink($filePath);

        $this->assertFalse($loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
    }

    public function filePathProvider(): array
    {
        return [
            [
                'Resources/config/test.yml',
                $this->getPath('/Resources/config/test.yml'),
                'Resources/config/test.yml'
            ],
            [
                '/Resources/config/test.yml',
                $this->getPath('/Resources/config/test.yml'),
                'Resources/config/test.yml'
            ],
            [
                'Resources\config\test.yml',
                $this->getPath('/Resources/config/test.yml'),
                'Resources/config/test.yml'
            ],
            [
                '\Resources\config\test.yml',
                $this->getPath('/Resources/config/test.yml'),
                'Resources/config/test.yml'
            ],
            [
                'test.yml',
                $this->getPath('/test.yml'),
                'test.yml'
            ],
            [
                '/test.yml',
                $this->getPath('/test.yml'),
                'test.yml'
            ],
            [
                '\test.yml',
                $this->getPath('/test.yml'),
                'test.yml'
            ],
        ];
    }

    public function testSerialization(): void
    {
        $relativeFilePath = 'Resources/config/test.yml';
        $loader = $this->getMockForAbstractClass(CumulativeFileLoader::class, [$relativeFilePath]);
        $serialized = serialize($loader);
        /** @var CumulativeFileLoader $unserialized */
        $unserialized = unserialize($serialized);
        $this->assertEquals('/Resources/config/test.yml', $unserialized->getRelativeFilePath());
        $this->assertEquals('Resources/config/test.yml', $unserialized->getResource());
        $reflection = new \ReflectionClass($unserialized);
        $this->assertEquals('test', $reflection->getProperty('resourceName')->getValue($unserialized));
    }
}
