<?php

namespace Zend\Mime\Decoder;

use InvalidArgumentException;

class HeaderParameter
{
    // rules for valid parameter name are as follows:
    // NOTE: rfc 2045 should be used for parameters parsing, not 2231
    // attribute := token := 1*<any (US-ASCII) CHAR except SPACE, CTLs, or tspecials> -- RFC 2045
    // attribute-char := <any (US-ASCII) CHAR except SPACE, CTLs, "*", "'", "%", or tspecials> -- RFC 2231
    // CTLs US-ASCII decimal 1-31, 127  -- RFC 822
    // tspecials "(", ")", "<", ">", "@",  ",", ";", ":", "\", <">, "/", "[", "]", "?", "=" -- RFC 2045

    protected static $tokenCharGroup = '!#$%&*+\-.0-9a-zA-Z^_|~';

    public static function parse($parameterString)
    {
        // inefficent implementation to establish what exactly is needed to be done
        $parameterString = trim($parameterString);
        // this pattern is a bitch to read. Should be somewhat optimized tho.
        // Until better pattern is available
        $pattern = '/^\s*+(?P<aname>';
        $pattern .= '(?P<name>[!#$%&+\-.0-9a-zA-Z^_|~]++[!#$%&*+\-.0-9a-zA-Z^_|~]*?)';
        $pattern .= '(?>\\*(?P<sect>\d+))?(?P<ext>\\*)?)\\s*+=';
        $pattern .= '\\s*+(?P<val>(?:[^;"]++|"(?:[^\\\"]|\\\.)*")+)\\s*+(?:;|$)/';

        $params = [];
        while (strlen($parameterString) > 0) {
            if (!preg_match($pattern, $parameterString, $matches)) {
                // malformed?
                throw new InvalidArgumentException('Malformed parameters string');
            }

            $parameterString = substr($parameterString, strlen($matches[0]));

            $name  = $matches['aname'];
            $value = $matches['val'];
            if (isset($value[0]) && $value[0] == '"' && substr($value, -1) == '"') {
                $value = substr(substr($value, 1), 0, -1);
                $params[$name] = stripslashes($value);
            } else {
                $params[$name] = $value;
            }
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
}
