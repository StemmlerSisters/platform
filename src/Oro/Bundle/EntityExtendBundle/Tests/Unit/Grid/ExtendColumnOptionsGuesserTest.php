<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Grid;

use Oro\Bundle\DataGridBundle\Extension\Formatter\Property\PropertyInterface as Property;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Entity\EnumOption;
use Oro\Bundle\EntityExtendBundle\Grid\ExtendColumnOptionsGuesser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Guess\Guess;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ExtendColumnOptionsGuesserTest extends TestCase
{
    private ConfigManager&MockObject $configManager;
    private ExtendColumnOptionsGuesser $guesser;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);

        $this->guesser = new ExtendColumnOptionsGuesser($this->configManager);
    }

    public function testGuessFormatterNoGuess(): void
    {
        $guess = $this->guesser->guessFormatter('TestClass', 'testProp', 'string');
        $this->assertNull($guess);
    }

    public function testGuessFilterNoGuess(): void
    {
        $guess = $this->guesser->guessFilter('TestClass', 'testProp', 'string');
        $this->assertNull($guess);
    }

    public function testGuessFormatterForEnumNoConfig(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $enumConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('enum')
            ->willReturn($enumConfigProvider);
        $enumConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(false);

        $guess = $this->guesser->guessFormatter($class, $property, 'enum');
        $this->assertNull($guess);
    }

    public function testGuessFilterForEnumNoConfig(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('extend')
            ->willReturn($extendConfigProvider);
        $extendConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(false);

        $guess = $this->guesser->guessFilter($class, $property, 'enum');
        $this->assertNull($guess);
    }

    public function testGuessFormatterForMultiEnumNoConfig(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $enumConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('enum')
            ->willReturn($enumConfigProvider);
        $enumConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(false);

        $guess = $this->guesser->guessFormatter($class, $property, 'multiEnum');
        $this->assertNull($guess);
    }

    public function testGuessFilterForMultiEnumNoConfig(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('extend')
            ->willReturn($extendConfigProvider);
        $extendConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(false);

        $guess = $this->guesser->guessFilter($class, $property, 'multiEnum');
        $this->assertNull($guess);
    }

    public function testGuessFormatterForEnum(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $config = new Config(new FieldConfigId('extend', $class, $property, 'enum'));
        $config->set('target_entity', 'Test\EnumValue');
        $config->set('enum_code', 'enum_code');

        $enumConfigProvider = $this->createMock(ConfigProvider::class);
        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('enum')
            ->willReturn($enumConfigProvider);
        $enumConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(true);
        $enumConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($class, $property)
            ->willReturn($config);

        $guess = $this->guesser->guessFormatter($class, $property, 'enum');
        $this->assertEquals(
            [
                'frontend_type' => Property::TYPE_HTML,
                'type' => 'twig',
                'template' => '@OroEntityExtend/Datagrid/Property/enum.html.twig',
                'context' => [
                    'enum_code' => $config->get('enum_code')
                ]
            ],
            $guess->getOptions()
        );
        $this->assertEquals(Guess::MEDIUM_CONFIDENCE, $guess->getConfidence());
    }

    public function testGuessSorterForEnum(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $guess = $this->guesser->guessSorter($class, $property, 'enum');
        $this->assertNull($guess);
    }

    public function testGuessFilterForEnum(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $config = new Config(new FieldConfigId('extend', $class, $property, 'enum'));
        $config->set('enum_code', 'enum_code');

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $enumConfigProvider = $this->createMock(ConfigProvider::class);

        $this->configManager->expects($this->exactly(2))
            ->method('getProvider')
            ->withConsecutive(['extend'], ['enum'])
            ->willReturnOnConsecutiveCalls($extendConfigProvider, $enumConfigProvider);

        $extendConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(true);
        $extendConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($class, $property)
            ->willReturn($config);

        $enumConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(true);
        $enumConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($class, $property)
            ->willReturn($config);

        $guess = $this->guesser->guessFilter($class, $property, 'enum');
        $this->assertEquals(
            [
                'type' => 'enum',
                'null_value' => ':empty:',
                'class' => EnumOption::class,
                'enum_code' => 'enum_code'
            ],
            $guess->getOptions()
        );
        $this->assertEquals(Guess::MEDIUM_CONFIDENCE, $guess->getConfidence());
    }

    public function testGuessFormatterForMultiEnum(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $config = new Config(new FieldConfigId('extend', $class, $property, 'enum'));
        $config->set('enum_code', 'enum_code');

        $enumConfigProvider = $this->createMock(ConfigProvider::class);

        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->with('enum')
            ->willReturn($enumConfigProvider);
        $enumConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(true);
        $enumConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($class, $property)
            ->willReturn($config);

        $guess = $this->guesser->guessFormatter($class, $property, 'multiEnum');
        $this->assertEquals(
            [
                'frontend_type' => Property::TYPE_HTML,
                'export_type' => 'list',
                'type' => 'twig',
                'template' => '@OroEntityExtend/Datagrid/Property/multiEnum.html.twig',
                'context' => [
                    'enum_code' => 'enum_code'
                ]
            ],
            $guess->getOptions()
        );
        $this->assertEquals(Guess::MEDIUM_CONFIDENCE, $guess->getConfidence());
    }

    public function testGuessSorterForMultiEnum(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $guess = $this->guesser->guessSorter($class, $property, 'multiEnum');
        $this->assertEquals(
            [
                'disabled' => true
            ],
            $guess->getOptions()
        );
        $this->assertEquals(Guess::MEDIUM_CONFIDENCE, $guess->getConfidence());
    }

    public function testGuessFilterForMultiEnum(): void
    {
        $class = 'TestClass';
        $property = 'testProp';

        $config = new Config(new FieldConfigId('extend', $class, $property, 'enum'));
        $config->set('enum_code', 'enum_code');

        $extendConfigProvider = $this->createMock(ConfigProvider::class);
        $enumConfigProvider = $this->createMock(ConfigProvider::class);

        $this->configManager->expects($this->exactly(2))
            ->method('getProvider')
            ->withConsecutive(['extend'], ['enum'])
            ->willReturnOnConsecutiveCalls($extendConfigProvider, $enumConfigProvider);

        $extendConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(true);
        $extendConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($class, $property)
            ->willReturn($config);

        $enumConfigProvider->expects($this->once())
            ->method('hasConfig')
            ->with($class, $property)
            ->willReturn(true);
        $enumConfigProvider->expects($this->once())
            ->method('getConfig')
            ->with($class, $property)
            ->willReturn($config);

        $guess = $this->guesser->guessFilter($class, $property, 'multiEnum');
        $this->assertEquals(
            [
                'type' => 'multi_enum',
                'null_value' => ':empty:',
                'class' => EnumOption::class,
                'enum_code' => 'enum_code'
            ],
            $guess->getOptions()
        );
        $this->assertEquals(Guess::MEDIUM_CONFIDENCE, $guess->getConfidence());
    }
}
