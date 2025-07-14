<?php

namespace Oro\Component\Config\Tests\Unit\Loader;

use Oro\Component\Config\CumulativeResource;
use Oro\Component\Config\CumulativeResourceInfo;
use Oro\Component\Config\Loader\CumulativeResourceLoaderCollection;
use Oro\Component\Config\Loader\FolderingCumulativeFileLoader;
use Oro\Component\Config\Loader\YamlCumulativeFileLoader;
use Oro\Component\Config\Tests\Unit\Fixtures\Bundle\TestBundle1\TestBundle1;
use Oro\Component\Testing\TempDirExtension;
use PHPUnit\Framework\TestCase;

class FolderingCumulativeFileLoaderTest extends TestCase
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

    public function testLoad(): void
    {
        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->createLoader();

        self::assertEquals($this->getExpectedResource(), $loader->getResource());

        /** @var CumulativeResourceInfo[] $result */
        $result = $loader->load($bundleClass, $bundleDir);

        self::assertIsArray($result);
        self::assertCount(3, $result);
        usort(
            $result,
            function (CumulativeResourceInfo $a, CumulativeResourceInfo $b) {
                return strcmp($a->path, $b->path);
            }
        );
        self::assertEquals(
            $this->getPath($bundleDir . '/Resources/config/another non word/test.yml'),
            $result[0]->path
        );
        self::assertEquals(
            $this->getPath($bundleDir . '/Resources/config/bar/test.yml'),
            $result[1]->path
        );
        self::assertEquals(
            $this->getPath($bundleDir . '/Resources/config/foo/test.yml'),
            $result[2]->path
        );
    }

    public function testRegisterResource(): void
    {
        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->createLoader();

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $found = $resource->getFound($bundleClass);
        sort($found);
        self::assertEquals(
            [
                $this->getPath($bundleDir . '/Resources/config/another non word/test.yml'),
                $this->getPath($bundleDir . '/Resources/config/bar/test.yml'),
                $this->getPath($bundleDir . '/Resources/config/foo/test.yml')
            ],
            $found
        );
    }

    public function testIsResourceFreshNoChanges(): void
    {
        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->createLoader();

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $loadTime = $this->getLastMTime($bundleDir) + 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        self::assertTrue($loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
    }

    public function testIsResourceFreshNoChangesWithFewLoaders(): void
    {
        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader1 = new FolderingCumulativeFileLoader('{folder}', '\w+', [
            new YamlCumulativeFileLoader('Resources/config/{folder}/test.yml'),
            new YamlCumulativeFileLoader('Resources/config/{folder}/none.yml'),
        ]);

        $loader2 = new FolderingCumulativeFileLoader('{folder}', '\w+', [
            new YamlCumulativeFileLoader('Resources/config/another non word/test.yml'),
        ]);

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader1->registerFoundResource($bundleClass, $bundleDir, '', $resource);
        $loader2->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $loadTime = $this->getLastMTime($bundleDir) + 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader1->registerFoundResource($bundleClass, $bundleDir, '', $resource);
        $loader2->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        self::assertTrue($loader1->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
        self::assertTrue($loader2->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
    }

    public function testIsResourceFreshExistingFileWasChanged(): void
    {
        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->createLoader();

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $loadTime = $this->getLastMTime($bundleDir) - 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        self::assertFalse($loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
    }

    public function testIsResourceFreshNewFileWasAdded(): void
    {
        $relativeFilePath = 'Resources/config/tmp/test.yml';

        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->createLoader();

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $loadTime = $this->getLastMTime($bundleDir) + 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $filePath = $bundleDir . '/' . $relativeFilePath;
        mkdir(dirname($filePath));
        file_put_contents($filePath, 'test: tmp');
        $result = $loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime);
        unlink($filePath);
        rmdir(dirname($filePath));
        self::assertFalse($result);
    }

    public function testIsResourceFreshNewFileWasDeleted(): void
    {
        $relativeFilePath = 'Resources/config/tmp/test.yml';

        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $loader = $this->createLoader();

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);

        $filePath = $bundleDir . '/' . $relativeFilePath;
        mkdir(dirname($filePath));
        file_put_contents($filePath, 'test: tmp');
        $loadTime = $this->getLastMTime($bundleDir) + 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, '', $resource);
        unlink($filePath);
        rmdir(dirname($filePath));

        self::assertFalse($loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, $loadTime));
    }

    public function testIsResourceFreshException(): void
    {
        $bundleClass = TestBundle1::class;
        $bundleDir = $this->bundleDir;

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());

        $ymlLoader = $this->createMock(YamlCumulativeFileLoader::class);
        $ymlLoader->expects($this->once())
            ->method('isResourceFresh')
            ->with($bundleClass, $bundleDir, '', $resource, 0)
            ->willThrowException(new \Exception('error'));

        $loader = new FolderingCumulativeFileLoader('{folder}', '\w+', $ymlLoader);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error');

        $loader->isResourceFresh($bundleClass, $bundleDir, '', $resource, 0);
    }

    public function testSerializationCallsInit(): void
    {
        $loader = $this->createLoader();
        $serialized = serialize($loader);
        $unserialized = unserialize($serialized);
        $reflection = new \ReflectionClass($unserialized);
        $this->assertEquals(
            $reflection->getProperty('folderPlaceholder')->getValue($loader),
            $reflection->getProperty('folderPlaceholder')->getValue($unserialized)
        );
        $this->assertEquals(
            $reflection->getProperty('folderPattern')->getValue($loader),
            $reflection->getProperty('folderPattern')->getValue($unserialized)
        );
        $this->assertSame(
            $reflection->getProperty('registeredRelativeFilePaths')->getValue($loader),
            $reflection->getProperty('registeredRelativeFilePaths')->getValue($unserialized)
        );

        $this->assertCount(
            count($reflection->getProperty('fileResourceLoaders')->getValue($loader)),
            $reflection->getProperty('fileResourceLoaders')->getValue($unserialized)
        );

        // this property is only populated if initialize was called
        $this->assertSame(
            $reflection->getProperty('preparedRelativeFilePaths')->getValue($loader),
            $reflection->getProperty('preparedRelativeFilePaths')->getValue($unserialized)
        );
    }

    private function createLoader(): FolderingCumulativeFileLoader
    {
        return new FolderingCumulativeFileLoader(
            '{folder}',
            '\w+',
            [
                new YamlCumulativeFileLoader('Resources/config/{folder}/test.yml'),
                new YamlCumulativeFileLoader('Resources/config/{folder}/none.yml'),
                new YamlCumulativeFileLoader('Resources/config/another non word/test.yml')
            ]
        );
    }

    private function getExpectedResource(): string
    {
        return
            'Foldering:'
            . 'Resources/config/{folder}/test.yml;'
            . 'Resources/config/{folder}/none.yml;'
            . 'Resources/config/another non word/test.yml';
    }

    private function getLastMTime(string $bundleDir): int
    {
        $files = [
            $bundleDir . '/Resources/config/another non word/test.yml',
            $bundleDir . '/Resources/config/bar/test.yml',
            $bundleDir . '/Resources/config/foo/test.yml'
        ];

        $result = 0;
        foreach ($files as $file) {
            $time = filemtime($file);
            if ($time > $result) {
                $result = $time;
            }
        }

        return $result;
    }
}
