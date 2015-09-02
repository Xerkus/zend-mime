<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Mime\Decoder;

use Zend\Mime\Decoder\HeaderParameter;
use InvalidArgumentException;

/**
 * @covers Zend\Mime\Decoder\HeaderParameter
 */
class HeaderParameterTest extends \PHPUnit_Framework_TestCase
{
    public function testParsesSimpleParameters()
    {
        $paramString = 'param1=value1; param2="value2"; param3="value 3"';
        $expectedParams = [
            'param1' => 'value1',
            'param2' => 'value2',
            'param3' => 'value 3'
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    /**
     *
     * @see http://tools.ietf.org/html/rfc2231
     */
    public function testParsesParameterValueContinuations()
    {
        $paramString = 'access-type=URL;
            URL*0="ftp://";
            URL*1="cs.utk.edu/pub/moore/bulk-mailer/bulk-mailer.tar"';
        $expectedParams = [
            'access-type' => 'URL',
            'URL' => 'ftp://cs.utk.edu/pub/moore/bulk-mailer/bulk-mailer.tar'
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    public function testParsesValueCharsetAndLanguage()
    {
        $paramString = "param*=us-ascii'en-us'Encoded%20value";
        $expectedParams = [
            'param' => 'Encoded value'
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    public function testParsesMixedCharsetAndContinuations()
    {
        $paramString = "title*0*=us-ascii'en'This%20is%20even%20more%20;
            title*1*=%2A%2A%2Afun%2A%2A%2A%20;
            title*2=\"isn't it!\"";
        $expectedParams = [
            'title' => "This is even more ***fun*** isn't it!", // not really!
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    public function testContinuationIgnoresMixedOrder()
    {
        $paramString = "title*1*=%2A%2A%2Afun%2A%2A%2A%20;
            title*0*=us-ascii'en'This%20is%20even%20more%20;
            title*2=\"isn't it!\"";
        $expectedParams = [
            'title' => "This is even more ***fun*** isn't it!", // not really!
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    public function testParametersWithoutLeadingSectionAreIgnored()
    {
        // i suppose this should be handled as regular parameter
        $paramString = 'access-type=URL;
            URL*1="cs.utk.edu/pub/moore/bulk-mailer/bulk-mailer.tar"';
        $expectedParams = [
            'access-type' => 'URL',
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    public function testSectionGapsStopContinuationProcessing()
    {
        // This is questionable approach and must be revisited
        // It should probably throw exception instead
        $paramString = 'continuation*0=part0;
            continuation*1=part1;
            continuation*3="not recognized as continuation"';
        $expectedParams = [
            'continuation' => 'part0part1',
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    public function testRegularParameterHavePriorityOverContinuation()
    {
        $paramString = 'param="regular value";
            param*0="continuated";
            param*1=" value"';
        $expectedParams = [
            'param' => 'regular value',
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);

        $paramString = 'param*0="continuated";
            param*1=" value";
            param="regular value"';
        $expectedParams = [
            'param' => 'regular value',
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    public function testParsesRecoverableInvalidParameterStrings()
    {
        $paramString = 'param="=?UTF-8?Q?=C3=A1z=C3=81Z09-=5F?="';
        $expectedParams = [
            'param' => 'ázÁZ09-_',
        ];
        $parsedParams = HeaderParameter::parse($paramString);
        $this->assertEquals($expectedParams, $parsedParams);
    }

    /**
     *
     * @dataProvider malformedParameterStrings
     */
    public function testParseInvalidShouldThrowException($paramString)
    {
        $this->setExpectedException(InvalidArgumentException::class);
        $parsedParams = HeaderParameter::parse($paramString);
    }

    /**
     * provides malformed parameter strings
     */
    public function malformedParameterStrings()
    {
        return [
            'missing closing quote' => ['param1=value1;param2="value2;param3=value3'],
            'missing opening quote' => ['param1=value1;param2=value2";param3=value3'],
            'quote in param name'   => ['param1=value1;pa"ram2=value2'],
        ];
    }
}
