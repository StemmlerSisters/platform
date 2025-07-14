<?php

namespace Oro\Bundle\DigitalAssetBundle\Tests\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\DigitalAssetBundle\Entity\DigitalAsset;
use Oro\Bundle\DigitalAssetBundle\Entity\Repository\DigitalAssetRepository;
use Oro\Bundle\DigitalAssetBundle\EventListener\DigitalAssetSourceChangedListener;
use Oro\Bundle\DigitalAssetBundle\Reflector\FileReflector;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DigitalAssetSourceChangedListenerTest extends TestCase
{
    use EntityTrait;

    private EntityManagerInterface&MockObject $em;
    private FileReflector&MockObject $fileReflector;
    private DigitalAssetSourceChangedListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->fileReflector = $this->createMock(FileReflector::class);

        $this->listener = new DigitalAssetSourceChangedListener($this->fileReflector);
    }

    public function testPostUpdateNoDigitalAssetParent(): void
    {
        $entity = new File();

        $this->em->expects($this->never())
            ->method('flush');
        $this->listener->postUpdate($entity, new LifecycleEventArgs($entity, $this->em));
    }

    public function testPostUpdate(): void
    {
        $sourceFile = (new File())
            ->setParentEntityClass(DigitalAsset::class)
            ->setParentEntityId($digitalAssetId = 1);

        $childFile1 = $this->getEntity(File::class, [
            'id' => 22,
            'filename' => 'old_image.jpg',
            'extension' => 'jpg'
        ]);

        $childFile2 = $this->getEntity(File::class, [
            'id' => 33,
            'filename' => 'old_image.jpg',
            'extension' => 'jpg'
        ]);

        $this->em->expects($this->once())
            ->method('getRepository')
            ->with(DigitalAsset::class)
            ->willReturn($repo = $this->createMock(DigitalAssetRepository::class));

        $repo->expects($this->once())
            ->method('findChildFilesByDigitalAssetId')
            ->with($digitalAssetId)
            ->willReturn([$childFile1, $childFile2]);

        $this->fileReflector->expects($this->exactly(2))
            ->method('reflectFromFile')
            ->withConsecutive(
                [$childFile1, $sourceFile],
                [$childFile2, $sourceFile]
            );

        $this->em->expects($this->once())
            ->method('flush');

        $this->listener->postUpdate($sourceFile, new LifecycleEventArgs($sourceFile, $this->em));
    }
}
