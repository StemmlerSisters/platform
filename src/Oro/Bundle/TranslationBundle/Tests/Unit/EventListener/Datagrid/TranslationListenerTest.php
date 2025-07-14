<?php

namespace Oro\Bundle\TranslationBundle\Tests\Unit\EventListener\Datagrid;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Datagrid;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\TranslationBundle\Entity\Language;
use Oro\Bundle\TranslationBundle\EventListener\Datagrid\TranslationListener;
use Oro\Bundle\TranslationBundle\Provider\LanguageProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TranslationListenerTest extends TestCase
{
    private LanguageProvider&MockObject $languageProvider;
    private TranslationListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->languageProvider = $this->createMock(LanguageProvider::class);

        $this->listener = new TranslationListener($this->languageProvider);
    }

    public function testOnBuildBefore(): void
    {
        $config = DatagridConfiguration::create([]);
        $datagrid = new Datagrid('test', $config, new ParameterBag());
        self::assertNull($datagrid->getParameters()->get('en_language'));

        $enLanguage = new Language();

        $this->languageProvider->expects(self::once())
            ->method('getDefaultLanguage')
            ->willReturn($enLanguage);

        $this->listener->onBuildBefore(new BuildBefore($datagrid, $config));

        self::assertSame($enLanguage, $datagrid->getParameters()->get('en_language'));
    }
}
