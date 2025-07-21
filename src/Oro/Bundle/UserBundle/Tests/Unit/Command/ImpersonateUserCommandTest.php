<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestEnumValue;
use Oro\Bundle\LocaleBundle\Formatter\DateTimeFormatterInterface;
use Oro\Bundle\UserBundle\Command\ImpersonateUserCommand;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserInterface;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Oro\Component\Testing\Command\CommandTestingTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ImpersonateUserCommandTest extends TestCase
{
    use CommandTestingTrait;

    private const USERNAME = 'someuser';

    private UserManager&MockObject $userManager;
    private ManagerRegistry&MockObject $managerRegistry;
    private ImpersonateUserCommand $command;

    public function testSuccessfulExecuteReturnsZeroAndSuggestsURL(): void
    {
        $this->createMocks();
        $commandTester = $this->executeCommand();

        $this->assertReturnsSuccessAndSuggestsUrl($commandTester);
    }

    public function testSuccessfulExecuteDisplaysWarningForDisabledUser(): void
    {
        $this->createMocks(false);

        $commandTester = $this->executeCommand();

        $this->assertReturnsSuccessAndSuggestsUrl($commandTester);
        $this->assertProducedWarning(
            $commandTester,
            'User account is disabled. You will not be able to login as this user.'
        );
    }

    public function testSuccessfulExecuteDisplaysWarningForNonActiveAuthStatus(): void
    {
        $this->createMocks(null, UserManager::STATUS_RESET);

        $commandTester = $this->executeCommand();

        $this->assertReturnsSuccessAndSuggestsUrl($commandTester);
        $this->assertProducedWarning(
            $commandTester,
            'You will not be able to login as this user until the auth status is changed to "Active".'
        );
    }

    public function testExecuteDisplaysErrorIfUserDoesntExist(): void
    {
        $this->createMocks(null, null, null, true);

        $commandTester = $this->executeCommand();

        $this->assertProducedError(
            $commandTester,
            sprintf('User with username "%s" does not exist.', self::USERNAME)
        );
    }

    public function testExecuteDisplaysErrorIfUnknownUserType(): void
    {
        $this->createMocks(null, null, null, null, $this->createMock(UserInterface::class));

        $commandTester = $this->executeCommand();

        $this->assertProducedError(
            $commandTester,
            sprintf('Unsupported user type, the user "%s" cannot be impersonated.', self::USERNAME)
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->userManager = $this->createMock(UserManager::class);
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);

        $this->command = new ImpersonateUserCommand(
            $this->managerRegistry,
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(ConfigManager::class),
            $this->userManager,
            $this->createMock(DateTimeFormatterInterface::class)
        );
    }

    private function createMocks(
        ?bool $userEnabled = null,
        ?string $authStatusId = null,
        ?string $userClass = null,
        ?bool $nullUserStub = null,
        ?object $userStub = null
    ): void {
        if (null === $userStub && true !== $nullUserStub) {
            $userStub = $this->getMockBuilder($userClass ?? User::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['isEnabled', 'getUserIdentifier'])
                ->addMethods(['getAuthStatus'])
                ->getMock();
            $userStub->expects(self::any())
                ->method('isEnabled')
                ->willReturn($userEnabled ?? true);
            $userStub->expects(self::any())
                ->method('getUserIdentifier')
                ->willReturn(self::USERNAME);
            $userStub->expects(self::any())
                ->method('getAuthStatus')
                ->willReturn(new TestEnumValue(
                    UserManager::AUTH_STATUS_ENUM_CODE,
                    'Test',
                    $authStatusId ?? UserManager::STATUS_ACTIVE
                ));
        }
        $this->userManager->expects(self::any())
            ->method('findUserByUsername')
            ->willReturn($userStub);

        $this->managerRegistry->expects(self::any())
            ->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));
    }

    private function executeCommand(): CommandTester
    {
        return $this->doExecuteCommand($this->command, [
            'username' => self::USERNAME,
        ]);
    }

    private function assertReturnsSuccessAndSuggestsUrl(CommandTester $commandTester): void
    {
        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'open the following URL');
    }
}
