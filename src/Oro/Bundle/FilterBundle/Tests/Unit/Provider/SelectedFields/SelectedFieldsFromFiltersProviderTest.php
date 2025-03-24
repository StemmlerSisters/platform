<?php

namespace Oro\Bundle\FilterBundle\Tests\Unit\Provider\SelectedFields;

use Oro\Bundle\DataGridBundle\Tests\Unit\Provider\SelectedFields\AbstractSelectedFieldsProviderTestCase;
use Oro\Bundle\FilterBundle\Grid\Extension\Configuration as FilterConfiguration;
use Oro\Bundle\FilterBundle\Provider\SelectedFields\SelectedFieldsFromFiltersProvider;

class SelectedFieldsFromFiltersProviderTest extends AbstractSelectedFieldsProviderTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new SelectedFieldsFromFiltersProvider($this->datagridStateProvider);
    }

    #[\Override]
    protected function expectGetConfiguration(array $configuration): void
    {
        $this->datagridConfiguration->expects(self::once())
            ->method('offsetGetByPath')
            ->with(FilterConfiguration::COLUMNS_PATH)
            ->willReturn($configuration);
    }
}
