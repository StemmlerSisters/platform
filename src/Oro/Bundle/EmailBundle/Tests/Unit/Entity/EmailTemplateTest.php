<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Entity;

use Oro\Bundle\EmailBundle\Entity\EmailTemplate;
use Oro\Bundle\EmailBundle\Entity\EmailTemplateTranslation;
use Oro\Bundle\EmailBundle\Model\EmailTemplateInterface;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Component\Testing\ReflectionUtil;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;
use PHPUnit\Framework\TestCase;

class EmailTemplateTest extends TestCase
{
    use EntityTestCaseTrait;

    public function testConstruct(): void
    {
        $template = new EmailTemplate('update_entity.html.twig', "@subject = sdfdsf\n abc");

        $this->assertSame('sdfdsf', $template->getSubject());
        $this->assertSame('abc', $template->getContent());

        // Default values
        $this->assertFalse($template->getIsSystem());
        $this->assertTrue($template->getIsEditable());
        $this->assertSame(EmailTemplateInterface::TYPE_HTML, $template->getType());
    }

    public function testProperties(): void
    {
        $template = new EmailTemplate();
        $this->assertPropertyAccessors($template, [
            ['id', 1],
            ['isSystem', true, false],
            ['isEditable', true, false],
            ['name', 'test_name', false],
            ['parent', 42],
            ['subject', 'Default subject'],
            ['content', 'Default content', false],
            ['entityName', User::class],
            ['type', EmailTemplateInterface::TYPE_HTML],
            ['owner', new User()],
            ['organization', new Organization()],
            ['visible', true]
        ]);

        $this->assertPropertyCollections($template, [
            ['translations', new EmailTemplateTranslation()],
        ]);
    }

    public function testClone(): void
    {
        $template = new EmailTemplate('original_name', 'original content', EmailTemplateInterface::TYPE_TEXT, true);
        ReflectionUtil::setId($template, 42);
        $template->setIsEditable(false);

        $originalLocalization = new EmailTemplateTranslation();
        $template->addTranslation($originalLocalization);

        $clone = clone $template;

        $this->assertNull($clone->getId());
        $this->assertEquals($clone->getParent(), $template->getId());
        $this->assertSame('original_name', $clone->getName());
        $this->assertSame('original content', $clone->getContent());
        $this->assertSame(EmailTemplateInterface::TYPE_TEXT, $clone->getType());

        $this->assertFalse($clone->getIsSystem());
        $this->assertTrue($clone->getIsEditable());

        $clonedLocalization = $clone->getTranslations()->first();
        $this->assertNotSame($originalLocalization, $clonedLocalization);
        $this->assertSame($clone, $clonedLocalization->getTemplate());
    }

    public function testParse(): void
    {
        $template = <<<TEXT
            @subject = value1
            @parameter = value2

            Demo content @400,500 = value3
            This line is required!
        TEXT;

        $parseResult = EmailTemplate::parseContent($template);

        self::assertEquals("Demo content @400,500 = value3\n    This line is required!", $parseResult['content']);
        self::assertEquals(['subject' => 'value1', 'parameter' => 'value2'], $parseResult['params']);
    }

    public function testToString(): void
    {
        $template = new EmailTemplate('template_name');
        $this->assertSame('template_name', (string)$template);
    }
}
