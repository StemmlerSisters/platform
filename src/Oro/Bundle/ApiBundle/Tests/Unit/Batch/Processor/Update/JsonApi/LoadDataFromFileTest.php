<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Batch\Processor\Update\JsonApi;

use Oro\Bundle\ApiBundle\Batch\Model\ChunkFile;
use Oro\Bundle\ApiBundle\Batch\Processor\Update\JsonApi\LoadDataFromFile;
use Oro\Bundle\ApiBundle\Tests\Unit\Batch\Processor\Update\BatchUpdateProcessorTestCase;
use Oro\Bundle\GaufretteBundle\FileManager;
use PHPUnit\Framework\MockObject\MockObject;

class LoadDataFromFileTest extends BatchUpdateProcessorTestCase
{
    private FileManager&MockObject $fileManager;
    private LoadDataFromFile $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->fileManager = $this->createMock(FileManager::class);
        $this->processor = new LoadDataFromFile();
    }

    public function testProcessWhenDataAlreadyLoaded(): void
    {
        $this->context->setResult('test');
        $this->processor->process($this->context);
    }

    public function testProcessWhenDataFileContainsJsonApiHeader(): void
    {
        $fileName = 'test_file.json';

        $fileContent = '{"jsonapi":{"version": "1.0"},'
            . '"data":['
            . '{"type":"test","attributes": {"name": "first"}},'
            . '{"type":"test","attributes": {"name": "second"}}]}';

        $this->fileManager->expects(self::once())
            ->method('getFileContent')
            ->with($fileName)
            ->willReturn($fileContent);

        $this->context->setFile(new ChunkFile($fileName, 1, 1));
        $this->context->setFileManager($this->fileManager);
        $this->processor->process($this->context);

        self::assertEquals(
            [
                [
                    'jsonapi' => ['version' => '1.0'],
                    'data'    => ['type' => 'test', 'attributes' => ['name' => 'first']]
                ],
                [
                    'jsonapi' => ['version' => '1.0'],
                    'data'    => ['type' => 'test', 'attributes' => ['name' => 'second']]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testProcessWhenDataFileDoesNotContainJsonApiHeader(): void
    {
        $fileName = 'test_file.json';

        $fileContent = '{"data":['
            . '{"type":"test","attributes": {"name": "first"}},'
            . '{"type":"test","attributes": {"name": "second"}}]}';

        $this->fileManager->expects(self::once())
            ->method('getFileContent')
            ->with($fileName)
            ->willReturn($fileContent);

        $this->context->setFile(new ChunkFile($fileName, 1, 1));
        $this->context->setFileManager($this->fileManager);
        $this->processor->process($this->context);

        self::assertEquals(
            [
                ['data' => ['type' => 'test', 'attributes' => ['name' => 'first']]],
                ['data' => ['type' => 'test', 'attributes' => ['name' => 'second']]]
            ],
            $this->context->getResult()
        );
    }
}
