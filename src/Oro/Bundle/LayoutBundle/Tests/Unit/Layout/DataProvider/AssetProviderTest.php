<?php

namespace Oro\Bundle\LayoutBundle\Tests\Unit\Layout\DataProvider;

use Oro\Bundle\LayoutBundle\Layout\DataProvider\AssetProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;

class AssetProviderTest extends TestCase
{
    private Packages&MockObject $packages;
    private AssetProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->packages = $this->createMock(Packages::class);

        $this->provider = new AssetProvider($this->packages);
    }

    /**
     * @dataProvider getUrlDataProvider
     */
    public function testGetUrl(string $path, ?string $packageName, ?string $normalizedPath, ?string $expected): void
    {
        $this->packages->expects($this->once())
            ->method('getUrl')
            ->with($normalizedPath, $packageName)
            ->willReturn($expected);

        $this->assertEquals($expected, $this->provider->getUrl($path, $packageName));
    }

    public function getUrlDataProvider(): array
    {
        return [
            'with_path_only'             => [
                'path'           => 'path',
                'packageName'    => null,
                'normalizedPath' => 'path',
                'expected'       => 'assets/path',
            ],
            'with_path_and_package_name' => [
                'path'           => 'path',
                'packageName'    => 'package',
                'normalizedPath' => 'path',
                'expected'       => 'assets/path',
            ],
            'with_full_path'             => [
                'path'           => '@AcmeTestBundle/Resources/public/images/Picture.png',
                'packageName'    => null,
                'normalizedPath' => 'bundles/acmetest/images/Picture.png',
                'expected'       => 'assets/bundles/acmetest/images/Picture.png',
            ],
            'with_non_bundle_path'       => [
                'path'           => '@AcmeTestBundle/Resources/public/images/Picture.png',
                'packageName'    => null,
                'normalizedPath' => 'bundles/acmetest/images/Picture.png',
                'expected'       => 'assets/@AcmeTest/Resources/public/images/Picture.png',
            ]
        ];
    }

    public function testGetUrlWithNullPath(): void
    {
        $this->packages->expects($this->never())
            ->method('getUrl');

        $this->assertNull($this->provider->getUrl(null));
    }

    public function testAddErrorForInvalidPathType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a string value for the path, got "array".');

        $this->packages->expects($this->never())
            ->method('getUrl');

        $this->provider->getUrl(['test']);
    }

    public function testAddErrorForInvalidPackageNameType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected null or a string value for the package name, got "array".');

        $this->packages->expects($this->never())
            ->method('getUrl');

        $this->provider->getUrl('test', ['test']);
    }
}
