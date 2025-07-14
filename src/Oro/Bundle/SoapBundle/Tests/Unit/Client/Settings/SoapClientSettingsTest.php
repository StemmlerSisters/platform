<?php

namespace Oro\Bundle\SoapBundle\Tests\Unit\Client\Settings;

use Oro\Bundle\SoapBundle\Client\Settings\SoapClientSettings;
use PHPUnit\Framework\TestCase;

class SoapClientSettingsTest extends TestCase
{
    public function testAccessors(): void
    {
        $wsdlFilePath = 'path';
        $methodName = 'method';
        $soapOptions = ['1', '2'];

        $settings = new SoapClientSettings($wsdlFilePath, $methodName, $soapOptions);

        self::assertSame($wsdlFilePath, $settings->getWsdlFilePath());
        self::assertSame($methodName, $settings->getMethodName());
        self::assertSame($soapOptions, $settings->getSoapOptions());
    }
}
