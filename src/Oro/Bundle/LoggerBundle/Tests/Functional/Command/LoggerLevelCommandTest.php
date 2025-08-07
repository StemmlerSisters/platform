<?php

namespace Oro\Bundle\LoggerBundle\Tests\Functional\Command;

use Oro\Bundle\ConfigBundle\Tests\Functional\Traits\ConfigManagerAwareTestTrait;
use Oro\Bundle\LoggerBundle\DependencyInjection\Configuration;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

/**
 * @dbIsolationPerTest
 */
class LoggerLevelCommandTest extends WebTestCase
{
    use ConfigManagerAwareTestTrait;

    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();
    }

    public function testRunCommandToUpdateUserScope(): void
    {
        $params = ['debug', '10 minutes', '--user=admin@example.com'];
        $result = self::runCommand('oro:logger:level', $params);
        $expectedContent = "Log level for user 'admin@example.com' is successfully set to 'debug' till";

        self::assertStringContainsString($expectedContent, $result);

        $disableAfter = new \DateTime('now', new \DateTimeZone('UTC'));
        $disableAfter->add(\DateInterval::createFromDateString($params[1]));

        $userConfigManager = self::getConfigManager('user');
        self::assertEquals(
            $params[0],
            $userConfigManager->get(Configuration::getFullConfigKey(Configuration::LOGS_LEVEL_KEY))
        );
        self::assertEqualsWithDelta(
            $disableAfter->getTimestamp(),
            $userConfigManager->get(Configuration::getFullConfigKey(Configuration::LOGS_TIMESTAMP_KEY)),
            10,
            'Failed asserting that disable after is correct.'
        );
    }

    public function testRunCommandToUpdateGlobalScope(): void
    {
        $configGlobal = self::getConfigManager();
        $params = ['warning', '15 minutes'];
        $result = self::runCommand('oro:logger:level', $params);
        $expectedContent = "Log level for global scope is set to 'warning' till";

        self::assertStringContainsString($expectedContent, $result);

        $disableAfter = new \DateTime('now', new \DateTimeZone('UTC'));
        $disableAfter->add(\DateInterval::createFromDateString($params[1]));

        self::assertEquals(
            $params[0],
            $configGlobal->get(Configuration::getFullConfigKey(Configuration::LOGS_LEVEL_KEY))
        );
        self::assertEqualsWithDelta(
            $disableAfter->getTimestamp(),
            $configGlobal->get(Configuration::getFullConfigKey(Configuration::LOGS_TIMESTAMP_KEY)),
            10,
            'Failed asserting that disable after is correct.'
        );
    }

    /**
     * @dataProvider runCommandWithFailedValidationDataProvider
     */
    public function testRunCommandWithFailedValidation(string $expectedContent, array $params): void
    {
        $result = self::runCommand('oro:logger:level', $params);

        self::assertStringContainsString($expectedContent, $result);
    }

    public function runCommandWithFailedValidationDataProvider(): array
    {
        return [
            'should show failed config update without required arguments' => [
                '$expectedContent' => 'Not enough arguments (missing: "level, disable-after")',
                '$params' => [],
            ],
            'should show failed config update with wrong level argument' => [
                '$expectedContent' => "Wrong 'wrong_level' value for 'level' argument",
                '$params' => ['wrong_level', '15 minutes'],
            ],
            'should show failed config update with wrong disable-after argument' => [
                '$expectedContent' => "Value '15' for 'disable-after' argument should be valid date interval",
                '$params' => ['debug', '15'],
            ],
            'should show failed config update for non existing user' => [
                '$expectedContent' => "User with email 'nonexist@user.com' not exists.",
                '$params' => ['debug', '15 minutes', '--user=nonexist@user.com'],
            ],
        ];
    }

    public function testCommandContainsHelp(): void
    {
        $result = self::runCommand('oro:logger:level', ['--help']);

        self::assertStringContainsString('Usage: oro:logger:level [options] [--] <level> <disable-after>', $result);
    }

    public function testRunCommandWithOverIntervalLimit(): void
    {
        $params = ['debug', '61 minutes', '--user=admin@example.com'];
        $result = self::runCommand('oro:logger:level', $params);
        $expectedContent = "Value 'disable-after' should be less than an hour";

        self::assertStringContainsString($expectedContent, $result);
    }
}
