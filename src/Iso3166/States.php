<?php namespace Academe\Opayo\Pi\Iso3166;

class States
{
    // Qualify by country, in case the state field gets extended to other
    // countries than the US.

    public static $states = array(
        'US' => array(
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'District of Columbia',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        ),
    );

    /**
     * @param $country
     * @return bool
     */
    public static function hasStates($country)
    {
        return isset(static::$states[$country]);
    }

    /**
     * The API states that state codes must be an ISO3166-2 code. These codes
     * all start with the country code, e.g. "US-AL". The documentation states this
     * gateway supports the two-chacater codes only.
     * @param $country
     * @param $code
     * @return bool
     */
    public static function isValid($country, $code)
    {
        return isset(static::$states[$country][$code]);
    }

    /**
     * @param string $country_code Optional country to return states for.
     * @return array
     */
    public static function getAll($country_code = null)
    {
        if (isset($country_code)) {
            return isset(static::$states[$country_code]) ? static::$states[$country_code] : [];
        }

        return static::$states;
    }
}
