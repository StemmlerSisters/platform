<?php

namespace Oro\Bundle\AddressBundle\Tests\Unit\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\AddressBundle\Entity\Repository\CountryRepository;
use Oro\Bundle\AddressBundle\Provider\CountryProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CountryProviderTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private CountryProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->provider = new CountryProvider($this->doctrine);
    }

    public function testGetCountryChoices(): void
    {
        $countryRepository = $this->createMock(CountryRepository::class);
        $this->doctrine->expects(self::once())
            ->method('getRepository')
            ->with(Country::class)
            ->willReturn($countryRepository);
        $countryRepository->expects(self::once())
            ->method('getCountries')
            ->willReturn([
                (new Country('iso2Code1'))->setName('name1'),
                (new Country('iso2Code2'))->setName('name2'),
            ]);

        self::assertEquals(
            [
                'name1' => 'iso2Code1',
                'name2' => 'iso2Code2',
            ],
            $this->provider->getCountryChoices()
        );
    }
}
