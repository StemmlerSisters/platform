<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Voter;

use Oro\Bundle\SecurityBundle\Acl\Voter\CollectionAssociationAwareVoter;
use Oro\Bundle\SecurityBundle\Tests\Unit\Fixtures\Models\CMS\CmsArticle;
use Oro\Bundle\SecurityBundle\Tests\Unit\Fixtures\Models\CMS\CmsUser;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class CollectionAssociationAwareVoterTest extends \PHPUnit\Framework\TestCase
{
    /** @var AuthorizationCheckerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $authorizationChecker;

    /** @var CollectionAssociationAwareVoter */
    private $voter;

    #[\Override]
    protected function setUp(): void
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->voter = new CollectionAssociationAwareVoter(
            $this->authorizationChecker,
            PropertyAccess::createPropertyAccessor(),
            CmsUser::class,
            'articles'
        );
    }

    private function getEntity(array $associatedEntities): CmsUser
    {
        $entity = new CmsUser();
        foreach ($associatedEntities as $associatedEntity) {
            $entity->addArticle($associatedEntity);
        }

        return $entity;
    }

    public function testVoteForNotObject(): void
    {
        self::assertEquals(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote(
                $this->createMock(TokenInterface::class),
                CmsUser::class,
                ['VIEW']
            )
        );
    }

    public function testVoteForNotSupportedEntity(): void
    {
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');

        self::assertEquals(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote(
                $this->createMock(TokenInterface::class),
                new CmsArticle(),
                ['VIEW']
            )
        );
    }

    /**
     * @dataProvider voteNullAssociationDataProvider
     */
    public function testVoteNullAssociation(array $attributes): void
    {
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');

        self::assertEquals(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote(
                $this->createMock(TokenInterface::class),
                $this->getEntity([]),
                $attributes
            )
        );
    }

    public static function voteNullAssociationDataProvider(): array
    {
        return [
            [['VIEW']],
            [['CREATE']],
            [['EDIT']],
            [['DELETE']],
            [['OTHER']]
        ];
    }

    public function testVoteWhenNotSupportedAttributes(): void
    {
        $this->authorizationChecker->expects(self::never())
            ->method('isGranted');

        self::assertEquals(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote(
                $this->createMock(TokenInterface::class),
                $this->getEntity([new CmsArticle()]),
                ['ATTR_1', 'ATTR_2']
            )
        );
    }

    /**
     * @dataProvider supportedAttributesDataProvider
     */
    public function testVoteWhenAccessToAssociatedEntityDenied(
        array $attributes,
        string $associatedEntityAttribute
    ): void {
        $associatedEntity1 = new CmsArticle();
        $associatedEntity2 = new CmsArticle();

        $this->authorizationChecker->expects(self::exactly(2))
            ->method('isGranted')
            ->willReturnMap([
                [$associatedEntityAttribute, $associatedEntity1, true],
                [$associatedEntityAttribute, $associatedEntity2, false],
            ]);

        self::assertEquals(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote(
                $this->createMock(TokenInterface::class),
                $this->getEntity([$associatedEntity1, $associatedEntity2]),
                $attributes
            )
        );
    }

    /**
     * @dataProvider supportedAttributesDataProvider
     */
    public function testVoteWhenAccessToAssociatedEntityGranted(
        array $attributes,
        string $associatedEntityAttribute
    ): void {
        $associatedEntity1 = new CmsArticle();
        $associatedEntity2 = new CmsArticle();

        $this->authorizationChecker->expects(self::exactly(2))
            ->method('isGranted')
            ->willReturnMap([
                [$associatedEntityAttribute, $associatedEntity1, true],
                [$associatedEntityAttribute, $associatedEntity2, true],
            ]);

        self::assertEquals(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote(
                $this->createMock(TokenInterface::class),
                $this->getEntity([$associatedEntity1, $associatedEntity2]),
                $attributes
            )
        );
    }

    public static function supportedAttributesDataProvider(): array
    {
        return [
            [['VIEW'], 'VIEW'],
            [['CREATE'], 'EDIT'],
            [['EDIT'], 'EDIT'],
            [['DELETE'], 'EDIT']
        ];
    }
}
