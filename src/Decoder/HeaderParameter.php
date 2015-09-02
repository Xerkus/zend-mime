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

    public static function parse($parameterString)
    {
        // inefficent ugly implementation to establish what exactly is needed to be done
        $parameterString = trim($parameterString);
        // this pattern is a bitch to read. Should be somewhat optimized tho.
        // Until better pattern is available
        $pattern = '/^\s*+(?P<name>';
        $pattern .= '(?P<ename>[!#$&+\-.0-9a-zA-Z^_|~]++)';
        $pattern .= '(?>\*(?P<sect>\d+))?(?P<ext>\*)?)\s*+=';
        $pattern .= '\s*+(?P<val>(?:[^;"]++|"(?:[^\\\"]|\\\.)*")+)\s*+(?:;|$)/';

        $params = [];
        $continuatedParams = [];
        $extendedParams = [];
        while (strlen($parameterString) > 0) {
            // This approach is chosen for the ability to fail on malformed
            // string instead of silently swallowing it and giving unexpected results
            //
            // I suppose actual implementation will be more error tolerant.
            // Also regex parsing alternatives have to be considered
            if (!preg_match($pattern, $parameterString, $matches)) {
                // malformed?
                throw new InvalidArgumentException('Malformed parameters string');
            }

            $parameterString = substr($parameterString, strlen($matches[0]));
            $matches['ext'] = !empty($matches['ext']);

            if ($matches['sect'] !== "") {
                $continuatedParams[$matches['ename']][$matches['sect']] = $matches;
                continue;
            }

            if ($matches['ext']) {
                $parts = explode("'", $matches['val'], 3);
                if (!isset($parts[2])) {
                    throw new InvalidArgumentException('Malformed extended parameter');
                }
                $encoding = $parts[0];
                $lang     = $parts[1];

                $extendedParams[$matches['ename']] = self::decodeExtendedValue(
                    $parts[2],
                    $encoding,
                    $lang
                );
                continue;
            }

            $params[$matches['name']] = self::decodeParamValue($matches['val']);
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
            if ($sections[0]['ext']) {
                $parts = explode("'", $sections[0]['val'], 3);
                if (!isset($parts[2])) {
                    throw new InvalidArgumentException('Malformed extended parameter');
                }
                $encoding = $parts[0];
                $lang     = $parts[1];

                $sections[0]['val'] = $parts[2];
            }

            $completeValue = '';
            for ($i = 0; array_key_exists($i, $sections); $i++) {
                if ($sections[$i]['ext']) {
                    $completeValue .= self::decodeExtendedValue(
                        $sections[$i]['val'],
                        $encoding,
                        $lang
                    );
                } else {
                    $completeValue .= self::decodeParamValue($sections[$i]['val']);
                }
            }
            $params[$name] = $completeValue;
        }
        return $params;
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
