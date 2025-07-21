<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Layout\DataProvider;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Layout\AttributeRenderRegistry;
use Oro\Bundle\EntityConfigBundle\Layout\DataProvider\AttributeGroupsTabsOptionsProvider;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class AttributeGroupsTabsOptionsProviderTest extends TestCase
{
    use EntityTrait;

    public function testGetConfig(): void
    {
        $attributeFamily = new AttributeFamily();
        $firstGroup = $this->getEntity(
            AttributeGroup::class,
            [
                'id' => 1,
                'code' => 'first_code',
                'labels' => new ArrayCollection([(new LocalizedFallbackValue())->setString('One')])
            ]
        );
        $secondGroup = $this->getEntity(
            AttributeGroup::class,
            [
                'id' => 2,
                'code' => 'second_code',
                'labels' => new ArrayCollection([(new LocalizedFallbackValue())->setString('Two')])
            ]
        );

        $attributeRenderRegistry = $this->createMock(AttributeRenderRegistry::class);
        $attributeRenderRegistry->expects($this->once())
            ->method('getNotRenderedGroups')
            ->with($attributeFamily)
            ->willReturn(new ArrayCollection([$firstGroup, $secondGroup]));

        $localizationHelper = $this->createMock(LocalizationHelper::class);
        $localizationHelper->expects($this->any())
            ->method('getLocalizedValue')
            ->willReturnMap([
                [$firstGroup->getLabels(), null, 'First'],
                [$secondGroup->getLabels(), null, 'Second']
            ]);

        $attributeGroupsTabsOptionsProvider = new AttributeGroupsTabsOptionsProvider(
            $attributeRenderRegistry,
            $localizationHelper
        );

        $this->assertEquals(
            [
                [
                    'id' => 'first_code',
                    'label' => 'First'
                ],
                [
                    'id' => 'second_code',
                    'label' => 'Second'
                ]
            ],
            $attributeGroupsTabsOptionsProvider->getTabsOptions($attributeFamily)
        );
    }
}
