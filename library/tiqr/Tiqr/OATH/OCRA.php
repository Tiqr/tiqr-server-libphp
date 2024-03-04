<?php 
/**
 * This file is part of the ocra-implementations package.
 *
 * More information: https://github.com/SURFnet/ocra-implementations/
 *
 * @author Ivo Jansch <ivo@egeniq.com>
 * 
 * @license See the LICENSE file in the source distribution
 */

/**
 * This a PHP port of the example implementation of the 
 * OATH OCRA algorithm.
 * Visit www.openauthentication.org for more information.
 *
 * @author Johan Rydell, PortWise (original Java)
 * @author Ivo Jansch, Egeniq (PHP port)
 */
class OCRA {

    private function __construct() {
        
    }

    /**
     * This method uses the hmac_hash function to provide the crypto
     * algorithm.
     * HMAC computes a Hashed Message Authentication Code with the
     * crypto hash algorithm as a parameter.
     *
     * @param String crypto     the crypto algorithm (sha1, sha256 or sha512)
     * @param String keyBytes   the bytes to use for the HMAC key
     * @param String text       the message or text to be authenticated.
     * @throws Exception
     */
    private static function _hmac(string $crypto, string $keyBytes, string $text) : string
    {
         $hash = hash_hmac($crypto, $text, $keyBytes);
         if (false === $hash) {
             throw new Exception("calculating hash_hmac failed");
         }
         return $hash;
    }

    /**
     * This method converts HEX string to Byte[]
     *
     * @param string $hex The hex string to decode
     * @param int $maxBytes The maximum length of the resulting decoded string
     * @param string $parameterName A descriptive name for the $hex parameter, used in exception error message
     * @return String a string with the decoded raw bytes of $hex
     * @throws InvalidArgumentException
     *
     * The length of the returned string will always be length($hex)/2 bytes
     * Note that $maxBytes is the max length of the returned string, not the max number of hex digits in $hex
     */
    private static function _hexStr2Bytes(string $hex, int $maxBytes, string $parameterName) : string
    {
        $len = strlen($hex);
        if ( ($len !== 0) && (! ctype_xdigit($hex)) ) {
            throw new InvalidArgumentException("Parameter '$parameterName' contains non hex digits");
        }
        if ( $len % 2 !== 0 ) {
            throw new InvalidArgumentException("Parameter '$parameterName' contains odd number of hex digits");
        }
        if ( $len > $maxBytes * 2) {
            throw new InvalidArgumentException("Parameter '$parameterName' too long");
        }
        // hex2bin logs PHP warnings when $hex contains invalid characters or has uneven length. Because we
        // check for these conditions above hex2bin() should always be silent
        $res=hex2bin($hex);
        if (false === $res) {
            throw new InvalidArgumentException("Parameter '$parameterName' could not be decoded");
        }
        return $res;
    }


    /**
     * Calculate the OCRA (RFC 6287) response for the given set of parameters
     * This implementation uses the same interface as the Java reference implementation from the RFC
     *
     * @param string $ocraSuite the OCRASuite
     * @param string $key the shared OCRA secret, HEX encoded
     * @param string $counter the counter (C), HEX encoded
     * @param string $question the challenge question (Q), HEX encoded
     * @param string $password the Hashed version of PIN/password (P), HEX encoded
     * @param string $sessionInformation the session information (S), HEX encoded
     * @param string $timeStamp the timestamp (T), HEX encoded
     *
     * @return string The Response, A numeric String in base 10 that includes number of digits specified in the OCRASuite
     * @throws InvalidArgumentException|Exception InvalidArgumentException is thrown when a HEX encoded parameter does not contain hex or when it exceeds its maximum length
     *
     * Note: The OCRA secret and the parameters C, Q, P, S and T must be provided as HEX encoded strings.
     *       How each parameter must be encoded is specified in the RFC
     *
     * In addition to the RFC reference implementation this implementation supports using "-S" in OCRASuite as an
     * alternative to "-S064"
     */
    static function generateOCRA(string $ocraSuite,
                                 string $key,
                                 string $counter,
                                 string $question,
                                 string $password,
                                 string $sessionInformation,
                                 string $timeStamp) : string
    {
        $codeDigits = 0;
        $crypto = "";
        $result = null;
        $ocraSuiteLength = strlen($ocraSuite);
        $counterLength = 0;
        $questionLength = 0;
        $passwordLength = 0;

        $sessionInformationLength = 0;
        $timeStampLength = 0;

        // Parse the cryptofunction
        // The cryptofucntion is defined as HOTP-<hash function>-<t>, where
        // <hash function> is one of sha1, sha256 or sha512
        // <t> is the truncation length: 0 (no truncation), 4-10
        $components = explode(":", $ocraSuite);
        $cryptoFunction = $components[1];
        $dataInput = strtolower($components[2]); // lower here so we can do case insensitive comparisons

        if(stripos($cryptoFunction, "hotp-sha1")!==false)
            $crypto = "sha1";
        elseif(stripos($cryptoFunction, "hotp-sha256")!==false)
            $crypto = "sha256";
        elseif(stripos($cryptoFunction, "hotp-sha512")!==false)
            $crypto = "sha512";
        else {
            throw new InvalidArgumentException('Unsupported OCRA CryptoFunction');
        }

        // The Cryptofucntion must ha a truncation of 0, 4-10
        $codeDigits_str = substr($cryptoFunction, strrpos($cryptoFunction, "-")+1);
        if (! ctype_digit($codeDigits_str)) {
            throw new InvalidArgumentException('Unsupported OCRA CryptoFunction');
        }
        $codeDigits = (integer)$codeDigits_str;
        if (($codeDigits != 0) && (($codeDigits < 4) || ($codeDigits > 10))) {
            throw new InvalidArgumentException('Unsupported OCRA CryptoFunction');
        }
                
        // The size of the byte array message to be encrypted
        // Counter
        if($dataInput[0] == "c" ) {
            // Fix the length of the HEX string
            while(strlen($counter) < 16)
                $counter = "0" . $counter;
            $counterLength=8;
        }
        // Question
        if($dataInput[0] == "q" ||
                stripos($dataInput, "-q")!==false) {
            while(strlen($question) < 256)
                $question = $question . "0";
            $questionLength=128;
        }

        // Password
        if(stripos($dataInput, "psha1")!==false) {
            while(strlen($password) < 40)
                $password = "0" . $password;
            $passwordLength=20;
        }
    
        if(stripos($dataInput, "psha256")!==false) {
            while(strlen($password) < 64)
                $password = "0" . $password;
            $passwordLength=32;
        }
        
        if(stripos($dataInput, "psha512")!==false) {
            while(strlen($password) < 128)
                $password = "0" . $password;
            $passwordLength=64;
        }
        
        // sessionInformation
        if(stripos($dataInput, "s064") !==false) {
            while(strlen($sessionInformation) < 128)
                $sessionInformation = "0" . $sessionInformation;

            $sessionInformationLength=64;
        } else if(stripos($dataInput, "s128") !==false) {
            while(strlen($sessionInformation) < 256)
                $sessionInformation = "0" . $sessionInformation;
        
            $sessionInformationLength=128;
        } else if(stripos($dataInput, "s256") !==false) {
            while(strlen($sessionInformation) < 512)
                $sessionInformation = "0" . $sessionInformation;
        
            $sessionInformationLength=256;
        } else if(stripos($dataInput, "s512") !==false) {
            while(strlen($sessionInformation) < 128)
                $sessionInformation = "0" . $sessionInformation;
        
            $sessionInformationLength=64;
        } else if (stripos($dataInput, "-s") !== false ) {
            // deviation from spec. Officially 's' without a length indicator is not in the reference implementation.
            // RFC is ambigious. However we have supported this in Tiqr since day 1, so we continue to support it.

            // See the format of the datainput below. "[]" denotes optional.
            // Because Q is mandatory, s will always be preceded by the separator "-". Matching "-s" is required
            // to prevent matching the "s" in the password input e.g. "psha1".
            // [C] | QFxx | [PH | Snnn | TG] : Challenge-Response computation
            // [C] | QFxx | [PH | TG] : Plain Signature computation
            while(strlen($sessionInformation) < 128)
                $sessionInformation = "0" . $sessionInformation;
            
            $sessionInformationLength=64;
        }
        
        
             
        // TimeStamp
        if($dataInput[0] == "t" ||
                stripos($dataInput, "-t") !== false) {
            while(strlen($timeStamp) < 16)
                $timeStamp = "0" . $timeStamp;
            $timeStampLength=8;
        }

        // Put the bytes of "ocraSuite" parameters into the message
        
        $msg = array_fill(0,$ocraSuiteLength+$counterLength+$questionLength+$passwordLength+$sessionInformationLength+$timeStampLength+1, 0);
                
        for($i=0;$i<strlen($ocraSuite);$i++) {
            $msg[$i] = $ocraSuite[$i];
        }
        
        // Delimiter
        $msg[strlen($ocraSuite)] = "\0";

        // Put the bytes of "Counter" to the message
        // Input is HEX encoded
        if($counterLength > 0 ) {
            $bArray = self::_hexStr2Bytes($counter, $counterLength, 'counter');
            for ($i=0;$i<strlen($bArray);$i++) {
                $msg [$i + $ocraSuiteLength + 1] = $bArray[$i];
            }
        }


        // Put the bytes of "question" to the message
        // Input is text encoded
        if($questionLength > 0 ) {
            $bArray = self::_hexStr2Bytes($question, $questionLength, 'question');
            for ($i=0;$i<strlen($bArray);$i++) {
                $msg [$i + $ocraSuiteLength + 1 + $counterLength] = $bArray[$i];
            }
        }

        // Put the bytes of "password" to the message
        // Input is HEX encoded
        if($passwordLength > 0){
            $bArray = self::_hexStr2Bytes($password, $passwordLength, 'password');
            for ($i=0;$i<strlen($bArray);$i++) {
                $msg [$i + $ocraSuiteLength + 1 + $counterLength + $questionLength] = $bArray[$i];
            }
        }

        // Put the bytes of "sessionInformation" to the message
        // Input is HEX encoded
        if($sessionInformationLength > 0 ){
            $bArray = self::_hexStr2Bytes($sessionInformation, $sessionInformationLength, 'sessionInformation');
            for ($i=0;$i<strlen($bArray);$i++) {
                $msg [$i + $ocraSuiteLength + 1 + $counterLength + $questionLength + $passwordLength] = $bArray[$i];
            }
        }

        // Put the bytes of "time" to the message
        // Input is HEX encoded value of minutes
        if($timeStampLength > 0){
            $bArray = self::_hexStr2Bytes($timeStamp, $timeStampLength, 'timeStamp');
            for ($i=0;$i<strlen($bArray);$i++) {
                $msg [$i + $ocraSuiteLength + 1 + $counterLength + $questionLength + $passwordLength + $sessionInformationLength] = $bArray[$i];
            }
        }
        
        $byteKey = self::_hexStr2Bytes($key, strlen($key)/2, 'key');
              
        $msg = implode("", $msg);

        $hash = self::_hmac($crypto, $byteKey, $msg);

        if ($codeDigits == 0)
            $result = $hash;
        else
            $result = self::_oath_truncate($hash, $codeDigits);
             
        return $result;
    }

    /**
     * Implementation of the Truncate function from RFC4226
     * Truncate a hex encoded (hash) result to a string of digital digits of $length
     *
     * @param string $hash hex encoded (hash) value to truncate. Minimum length is 20 bytes (i.e. a string of 40 hex digits)
     * @param int $length number of decimal digits to truncate to
     * @return string of $length digits
     */    
    static function _oath_truncate(string $hash, int $length = 6) : string
    {
        // Convert to dec
        foreach(str_split($hash,2) as $hex)
        {
            $hmac_result[]=hexdec($hex);
        }
    
        // Find offset
        $offset = $hmac_result[count($hmac_result) - 1] & 0xf;
    
        $v = strval(
            (($hmac_result[$offset+0] & 0x7f) << 24 ) |
            (($hmac_result[$offset+1] & 0xff) << 16 ) |
            (($hmac_result[$offset+2] & 0xff) << 8 ) |
            ($hmac_result[$offset+3] & 0xff)
        );

        // Prefix truncated string with 0's to ensure it always has the required length
        $v=str_pad($v, $length, "0", STR_PAD_LEFT);

        $v = substr($v, strlen($v) - $length);
        return $v;
    }
    
}
