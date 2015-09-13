<?php

namespace Zend\Mime\Decoder;

use InvalidArgumentException;

class HeaderParameter
{
    // rules for valid parameter name are as follows:
    // attribute-char := <any (US-ASCII) CHAR except SPACE, CTLs, "*", "'", "%", or tspecials> -- RFC 2231
    // CTLs US-ASCII decimal 1-31, 127  -- RFC 822
    // tspecials "(", ")", "<", ">", "@",  ",", ";", ":", "\", <">, "/", "[", "]", "?", "=" -- RFC 2045

    protected static $tokenCharGroup = '!#$%&*+\-.0-9a-zA-Z^_|~';

    /**
     * parses parameters string into array of parameters
     * inefficent ugly implementation to establish what exactly is needed to be done
     *
     * @param mixed $parameterString
     * @return void
     */
    public static function parse($parameterString)
    {
        $rawParams = static::parseIntoRaw($parameterString);

        $params = [];
        $continuatedParams = [];
        $extendedParams = [];
        foreach ($rawParams as $rawParam) {
            if ($rawParam['section'] !== false) {
                $continuatedParams[$rawParam['ename']][$rawParam['section']] = $rawParam;
                continue;
            }

            if ($rawParam['extended']) {
                $parts = explode("'", $rawParam['value'], 3);
                if (!isset($parts[2])) {
                    throw new InvalidArgumentException('Malformed extended parameter');
                }
                $encoding = $parts[0];
                $lang     = $parts[1];

                $extendedParams[$rawParam['ename']] = self::decodeExtendedValue(
                    $parts[2],
                    $encoding,
                    $lang
                );
                continue;
            }

            $params[$rawParam['name']] = self::decodeParamValue($rawParam['value']);
        }

        //merge params, regular taking priority over extended
        $params = $extendedParams + $params;

        foreach ($continuatedParams as $name => $sections) {
            if (isset($params[$name])) {
                // regular parameter is present and it takes precedence
                continue;
            }
            if (!isset($sections[0])) {
                // extended parameter missing leading section
                // skip it.
                continue;
            }

            $encoding = false;
            $lang = false;
            if ($sections[0]['extended']) {
                $parts = explode("'", $sections[0]['value'], 3);
                if (!isset($parts[2])) {
                    throw new InvalidArgumentException('Malformed extended parameter');
                }
                $encoding = $parts[0];
                $lang     = $parts[1];

                $sections[0]['value'] = $parts[2];
            }

            $completeValue = '';
            for ($i = 0; array_key_exists($i, $sections); $i++) {
                if ($sections[$i]['extended']) {
                    $completeValue .= self::decodeExtendedValue(
                        $sections[$i]['value'],
                        $encoding,
                        $lang
                    );
                } else {
                    $completeValue .= self::decodeParamValue($sections[$i]['value']);
                }
            }
            $params[$name] = $completeValue;
        }
        return $params;
    }

    /**
     * Parses parameter string into raw unprocessed parameters
     *
     * @param string $parameterString
     * @return array
     */
    public static function parseIntoRaw($parameterString)
    {
        $parameterString = trim($parameterString);
        // this pattern is a bitch to read. Should be somewhat optimized tho.
        // Until better pattern is available
        $pattern = '/\G\s*+(?P<name>';
        $pattern .= '(?P<ename>[!#$&+\-.0-9a-zA-Z^_|~]++)';
        $pattern .= '(?>\*(?P<sect>\d+))?(?P<ext>\*)?)\s*+=';
        $pattern .= '\s*+(?P<val>(?:[^;"]++|"(?:[^\\\"]|\\\.)*")+)\s*+(?:;|$)/';

        if ($parameterString === '') {
            return array();
        }

        // non-empty parameter string must have at least one parameter
        if (!preg_match_all($pattern, $parameterString, $matches, PREG_SET_ORDER)) {
            // malformed?
            throw new InvalidArgumentException('Malformed parameters string');
        }
        $length = 0;
        // calculate matched length and beautify matches
        array_walk($matches, function(&$match) use (&$length) {
            $length += strlen($match['0']);
            $match = [
                'name' => $match['name'],
                'ename' => $match['ename'],
                'extended' => !empty($match['ext']),
                'section' => $match['sect'] !== '' ? (int)$match['sect'] : false,
                'value' => $match['val']
            ];
        });

        // parameter string was not parsed completely, malformed syntax
        if ($length < strlen($parameterString)) {
            throw new InvalidArgumentException('Malformed parameters string');
        }

        return $matches;
    }

    public static function encode(array $parameters)
    {
    }


    /**
     * checkToken
     *
     * @param string $token test against token definition
     * @return bool
     *
     * @see http://tools.ietf.org/html/rfc2616#section-2.2
     */
    protected static function isToken($token)
    {
        return (bool)preg_match('/^[' . self::$tokenCharGroup . ']+$/', $token);
    }

    protected static function decodeParamValue($value)
    {
        // @TODO revisit this copied code. Most likely it behaves funny
        if (isset($value[0]) && $value[0] == '"' && substr($value, -1) == '"') {
            $value = substr(substr($value, 1), 0, -1);
            $value = stripslashes($value);
        }

        // attempt decoding encoded word
        // @TODO check if it looks like encoded word before making attempt at decoding
        if ('encodedWord' == 'encodedWord') {
            // @TODO verify this copypasted method works as expected
            $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            return $decoded;
        }

        return $value;
    }

    protected static function decodeExtendedValue($value, $charset, $lang)
    {
        // @TODO handle charset conversion!
        // @TODO replace this hack with proper decoder
        return urldecode($value);
    }
}
