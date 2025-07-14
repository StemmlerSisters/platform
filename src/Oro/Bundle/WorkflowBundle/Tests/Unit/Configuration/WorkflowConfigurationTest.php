<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Configuration;

use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class WorkflowConfigurationTest extends TestCase
{
    public function testProcessConfiguration(): void
    {
        $workflowConfiguration = new WorkflowConfiguration();

        $inputConfiguration = $this->getInputConfiguration();
        $expectedConfiguration = $this->getExpectedConfiguration();

        foreach ($inputConfiguration as $name => $configuration) {
            $this->assertArrayHasKey($name, $expectedConfiguration);
            $actualConfiguration = $workflowConfiguration->processConfiguration($configuration);
            $this->assertEquals($expectedConfiguration[$name], $actualConfiguration);
        }
    }

    private function getInputConfiguration(): array
    {
        $fileName = __DIR__ . '/Stub/CorrectConfiguration/Resources/config/oro/workflows.yml';
        $this->assertFileExists($fileName);
        $data = Yaml::parse(file_get_contents($fileName)) ?: [];

        return current($data);
    }

    private function getExpectedConfiguration(): array
    {
        $fileName = __DIR__ . '/Stub/CorrectConfiguration/Resources/config/oro/workflows.php';
        $this->assertFileExists($fileName);

        return include $fileName;
    }
}
