<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Cache\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Oro\Bundle\LocaleBundle\Cache\Normalizer\LocalizedFallbackValueCollectionNormalizer;
use Oro\Bundle\LocaleBundle\Cache\Normalizer\LocalizedFallbackValueNormalizer;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Model\FallbackType;
use Oro\Bundle\LocaleBundle\Tests\Unit\Form\Type\Stub\CustomLocalizedFallbackValueStub;
use Oro\Bundle\LocaleBundle\Tests\Unit\Stub\LocalizationStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LocalizedFallbackValueCollectionNormalizerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LocalizedFallbackValueCollectionNormalizer $collectionNormalizer;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->expects(self::any())
            ->method('getReference')
            ->with(Localization::class)
            ->willReturnCallback(function (string $class, int $id) {
                return new LocalizationStub($id);
            });

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::any())
            ->method('getManagerForClass')
            ->willReturn($this->entityManager);

        $this->collectionNormalizer = new LocalizedFallbackValueCollectionNormalizer(
            new LocalizedFallbackValueNormalizer(
                ['id' => 'i', 'string' => 's', 'localization' => 'l', 'fallback' => 'f'],
                $doctrine
            )
        );
    }

    private function createClassMetadata(string $className): ClassMetadata
    {
        $classMetadata = new ClassMetadata($className);
        $classMetadata->mapField(['fieldName' => 'id']);
        $classMetadata->mapField(['fieldName' => 'string']);
        $classMetadata->mapField(['fieldName' => 'fallback']);
        $classMetadata->wakeupReflection(new RuntimeReflectionService());

        return $classMetadata;
    }

    public function testNormalizeWhenEmpty(): void
    {
        $this->entityManager->expects(self::never())
            ->method('getClassMetadata');

        self::assertEquals([], $this->collectionNormalizer->normalize(new ArrayCollection()));
    }

    /**
     * @dataProvider normalizeDataProvider
     */
    public function testNormalize(Collection $localizedFallbackValues, array $expected): void
    {
        $this->entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with(LocalizedFallbackValue::class)
            ->willReturn($this->createClassMetadata(LocalizedFallbackValue::class));

        self::assertEquals($expected, $this->collectionNormalizer->normalize($localizedFallbackValues));
    }

    public function normalizeDataProvider(): array
    {
        return [
            'empty localized fallback values' => [
                'localizedFallbackValues' => new ArrayCollection(
                    [new LocalizedFallbackValue(), new LocalizedFallbackValue()]
                ),
                'expected' => [[], []]
            ],
            'with fields' => [
                'localizedFallbackValues' => new ArrayCollection([
                    (new LocalizedFallbackValue())
                        ->setString('sample string')
                        ->setFallback(FallbackType::SYSTEM),
                    (new LocalizedFallbackValue())
                        ->setString('sample string2')
                        ->setFallback(FallbackType::PARENT_LOCALIZATION)
                ]),
                'expected' => [
                    ['s' => 'sample string', 'f' => FallbackType::SYSTEM],
                    ['s' => 'sample string2', 'f' => FallbackType::PARENT_LOCALIZATION]
                ]
            ],
            'with localization' => [
                'localizedFallbackValues' => new ArrayCollection([
                    (new LocalizedFallbackValue())
                        ->setString('sample string')
                        ->setFallback(FallbackType::SYSTEM),
                    (new LocalizedFallbackValue())
                        ->setString('sample string2')
                        ->setFallback(FallbackType::PARENT_LOCALIZATION)
                        ->setLocalization(new LocalizationStub(42))
                ]),
                'expected' => [
                    ['s' => 'sample string', 'f' => FallbackType::SYSTEM],
                    ['s' => 'sample string2', 'f' => FallbackType::PARENT_LOCALIZATION, 'l' => 42]
                ]
            ]
        ];
    }

    public function testDenormalizeWhenEmpty(): void
    {
        $this->entityManager->expects(self::never())
            ->method('getClassMetadata');

        self::assertEquals(
            new ArrayCollection(),
            $this->collectionNormalizer->denormalize([], LocalizedFallbackValue::class)
        );
    }

    /**
     * @dataProvider denormalizeDataProvider
     */
    public function testDenormalize(
        array $normalizedData,
        string $className,
        Collection $expected
    ): void {
        $this->entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with($className)
            ->willReturn($this->createClassMetadata($className));

        self::assertEquals($expected, $this->collectionNormalizer->denormalize($normalizedData, $className));
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function denormalizeDataProvider(): array
    {
        return [
            'empty' => [
                'normalizedData' => [[], []],
                'className' => LocalizedFallbackValue::class,
                'expected' => new ArrayCollection([new LocalizedFallbackValue(), new LocalizedFallbackValue()]),
            ],
            'with fields' => [
                'normalizedData' => [
                    ['s' => 'sample string', 'f' => FallbackType::SYSTEM],
                    ['s' => 'sample string2', 'f' => FallbackType::PARENT_LOCALIZATION]
                ],
                'className' => LocalizedFallbackValue::class,
                'expected' => new ArrayCollection([
                    (new LocalizedFallbackValue())
                        ->setString('sample string')
                        ->setFallback(FallbackType::SYSTEM),
                    (new LocalizedFallbackValue())
                        ->setString('sample string2')
                        ->setFallback(FallbackType::PARENT_LOCALIZATION)
                ]),
            ],
            'with fields (old format)' => [
                'normalizedData' => [
                    ['string' => 'sample string', 'fallback' => FallbackType::SYSTEM],
                    ['string' => 'sample string2', 'fallback' => FallbackType::PARENT_LOCALIZATION]
                ],
                'className' => LocalizedFallbackValue::class,
                'expected' => new ArrayCollection([
                    (new LocalizedFallbackValue())
                        ->setString('sample string')
                        ->setFallback(FallbackType::SYSTEM),
                    (new LocalizedFallbackValue())
                        ->setString('sample string2')
                        ->setFallback(FallbackType::PARENT_LOCALIZATION)
                ])
            ],
            'with localization' => [
                'normalizedData' => [
                    ['s' => 'sample string', 'f' => FallbackType::SYSTEM],
                    ['s' => 'sample string2', 'f' => FallbackType::PARENT_LOCALIZATION, 'l' => 42]
                ],
                'className' => LocalizedFallbackValue::class,
                'expected' => new ArrayCollection([
                    (new LocalizedFallbackValue())
                        ->setString('sample string')
                        ->setFallback(FallbackType::SYSTEM),
                    (new LocalizedFallbackValue())
                        ->setString('sample string2')
                        ->setFallback(FallbackType::PARENT_LOCALIZATION)
                        ->setLocalization(new LocalizationStub(42))
                ])
            ],
            'with localization (old format)' => [
                'normalizedData' => [
                    ['string' => 'sample string', 'fallback' => FallbackType::SYSTEM],
                    [
                        'string' => 'sample string2',
                        'fallback' => FallbackType::PARENT_LOCALIZATION,
                        'localization' => ['id' => 42]
                    ]
                ],
                'className' => LocalizedFallbackValue::class,
                'expected' => new ArrayCollection([
                    (new LocalizedFallbackValue())
                        ->setString('sample string')
                        ->setFallback(FallbackType::SYSTEM),
                    (new LocalizedFallbackValue())
                        ->setString('sample string2')
                        ->setFallback(FallbackType::PARENT_LOCALIZATION)
                        ->setLocalization(new LocalizationStub(42))
                ])
            ],
            'with custom class' => [
                'normalizedData' => [
                    ['s' => 'sample string', 'f' => FallbackType::SYSTEM],
                    ['s' => 'sample string2', 'f' => FallbackType::PARENT_LOCALIZATION, 'l' => 42]
                ],
                'className' => CustomLocalizedFallbackValueStub::class,
                'expected' => new ArrayCollection([
                    (new CustomLocalizedFallbackValueStub())
                        ->setString('sample string')
                        ->setFallback(FallbackType::SYSTEM),
                    (new CustomLocalizedFallbackValueStub())
                        ->setString('sample string2')
                        ->setFallback(FallbackType::PARENT_LOCALIZATION)
                        ->setLocalization(new LocalizationStub(42))
                ])
            ],
            'with custom class (old format)' => [
                'normalizedData' => [
                    ['string' => 'sample string', 'fallback' => FallbackType::SYSTEM],
                    [
                        'string' => 'sample string2',
                        'fallback' => FallbackType::PARENT_LOCALIZATION,
                        'localization' => ['id' => 42]
                    ]
                ],
                'className' => CustomLocalizedFallbackValueStub::class,
                'expected' => new ArrayCollection([
                    (new CustomLocalizedFallbackValueStub())
                        ->setString('sample string')
                        ->setFallback(FallbackType::SYSTEM),
                    (new CustomLocalizedFallbackValueStub())
                        ->setString('sample string2')
                        ->setFallback(FallbackType::PARENT_LOCALIZATION)
                        ->setLocalization(new LocalizationStub(42))
                ])
            ]
        ];
    }
}
