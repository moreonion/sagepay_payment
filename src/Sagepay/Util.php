<?php

namespace Drupal\sagepay_payment\Sagepay;

define("MASK_FOR_HIDDEN_FIELDS", "...");
/**
 * Common utilities shared by all Integration methods
 */
class Util
{
     
    /**
     * The associated array containing card types and values
     *
     * @return array Array of card codes.
     */
    static protected $cardNames = array(
        'visa' => 'Visa',
        'visaelectron' => 'Visa Electron',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
        'delta' => 'Delta',
        'dc' => 'Diners Club',
        'jcb' => 'JCB',
        'laser' => 'Laser',
        'maestro' => 'Maestro',
    );

    /**
     * The card types that SagePay supports.
     *
     * @return array Array of card codes.
     */
    static public function cardTypes()
    {
        return array_keys(self::$cardNames);
    }

    /**
     * Populate the card names in to a usable array.
     *
     * @param array $availableCards Available card codes.
     *
     * @return array Array of card codes and names.
     */
    static public function availableCards(array $availableCards)
    {
        $cardArr = array();

        // Filter input card types
        foreach ($availableCards as $code)
        {
            $code = strtolower($code);
            if ((array_key_exists($code, self::$cardNames)))
            {
                $cardArr[$code] = self::$cardNames[$code];
            }
        }

        return $cardArr;
    }

    /**
     * Convert a data array to a query string ready to post.
     *
     * @param  array   $data        The data array.
     * @param  string  $delimeter   Delimiter used in query string
     * @param  boolean $urlencoded  If true encode the final query string
     *
     * @return string The array as a string.
     */
    static public function arrayToQueryString(array $data, $delimiter = '&', $urlencoded = false)
    {
        $queryString = '';
        $delimiterLength = strlen($delimiter);

        // Parse each value pairs and concate to query string
        foreach ($data as $name => $value)
        {   
            // Apply urlencode if it is required
            if ($urlencoded)
            {
                $value = urlencode($value);
            }
            $queryString .= $name . '=' . $value . $delimiter;
        }

        // remove the last delimiter
        return substr($queryString, 0, -1 * $delimiterLength);
    }

    static public function arrayToQueryStringRemovingSensitiveData(array $data,array $nonSensitiveDataKey, $delimiter = '&', $urlencoded = false)
    {
        $queryString = '';
        $delimiterLength = strlen($delimiter);

        // Parse each value pairs and concate to query string
        foreach ($data as $name => $value)
        {
           if (!in_array($name, $nonSensitiveDataKey)){
				$value=MASK_FOR_HIDDEN_FIELDS;
		   }
		   else if ($urlencoded){
				$value = urlencode($value);
		   }
           	// Apply urlencode if it is required
            	
           $queryString .= $name . '=' . $value . $delimiter;
        }

        // remove the last delimiter
        return substr($queryString, 0, -1 * $delimiterLength);
    }
    /**
     * Convert string to data array.
     *
     * @param string  $data       Query string
     * @param string  $delimeter  Delimiter used in query string
     *
     * @return array
     */
    static public function queryStringToArray($data, $delimeter = "&")
    {
        // Explode query by delimiter
        $pairs = explode($delimeter, $data);
        $queryArray = array();

        // Explode pairs by "="
        foreach ($pairs as $pair)
        {
            $keyValue = explode('=', $pair);

            // Use first value as key
            $key = array_shift($keyValue);

            // Implode others as value for $key
            $queryArray[$key] = implode('=', $keyValue);
        }
        return $queryArray;
    }

   static public function queryStringToArrayRemovingSensitiveData($data, $delimeter = "&", $nonSensitiveDataKey)
    {  
        // Explode query by delimiter
        $pairs = explode($delimeter, $data);
        $queryArray = array();

        // Explode pairs by "="
        foreach ($pairs as $pair)
        {
            $keyValue = explode('=', $pair);
            // Use first value as key
            $key = array_shift($keyValue);
            if (in_array($key, $nonSensitiveDataKey)){
			  $keyValue = explode('=', $pair);
			}
			else{
			  $keyValue = array(MASK_FOR_HIDDEN_FIELDS);
			}
		    // Implode others as value for $key
			$queryArray[$key] = implode('=', $keyValue);
    		
        }
        return $queryArray;
    }
    /**
     * Logging the debugging information to "debug.log"
     *
     * @param  string  $message
     * @return boolean
     */
    static public function log($message)
    {
        $settings = Settings::getInstance();
        if ($settings->getLogError())
        {
            $filename = SAGEPAY_SDK_PATH . '/debug.log';
            $line = '[' . date('Y-m-d H:i:s') . '] :: ' . $message;
            try
            {
                $file = fopen($filename, 'a+');
                fwrite($file, $line . PHP_EOL);
                fclose($file);
            } catch (Exception $ex)
            {
                return false;
            }
        }
        return true;
    }

    /**
     * Extract last 4 digits from card number;
     *
     * @param string $cardNr
     *
     * @return string
     */
    static public function getLast4Digits($cardNr)
    {
        // Apply RegExp to extract last 4 digits
        $matches = array();
        if (preg_match('/\d{4}$/', $cardNr, $matches))
        {
            return $matches[0];
        }
        return '';
    }

}
