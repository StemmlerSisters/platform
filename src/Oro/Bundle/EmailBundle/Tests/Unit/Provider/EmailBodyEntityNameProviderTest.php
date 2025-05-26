<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Provider;

use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailBody;
use Oro\Bundle\EmailBundle\Provider\EmailBodyEntityNameProvider;
use PHPUnit\Framework\TestCase;

class EmailBodyEntityNameProviderTest extends TestCase
{
    private EmailBodyEntityNameProvider $emailBodyEntityNameProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->emailBodyEntityNameProvider = new EmailBodyEntityNameProvider();
    }

    /**
     * @dataProvider getNameProvider
     */
    public function testGetName(?object $entity, string|bool $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->emailBodyEntityNameProvider->getName(null, null, $entity));
    }

    public function getNameProvider(): array
    {
        return [
            'text body' => [
                (new EmailBody())
                    ->setBodyIsText(true)
                    ->setTextBody('text body')
                    ->setBodyContent('body content'),
                'text body'
            ],
            'non text body' => [
                (new EmailBody())
                    ->setBodyIsText(false)
                    ->setTextBody('text body')
                    ->setBodyContent('body content'),
                'body content'
            ],
            'null' => [
                null,
                false,
            ],
            'different entity' => [
                new Email(),
                false,
            ],
        ];
    }

    /**
     * @dataProvider getNameDQLProvider
     */
    public function testGetNameDQL(string $className, string|bool $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            $this->emailBodyEntityNameProvider->getNameDQL(null, null, $className, 'alias')
        );
    }

    public function getNameDQLProvider(): array
    {
        return [
            'email body' => [
                EmailBody::class,
                'CASE WHEN alias.bodyIsText = true THEN alias.textBody ELSE alias.bodyContent END',
            ],
            'different entity' => [
                Email::class,
                false,
            ],
        ];
    }
}
