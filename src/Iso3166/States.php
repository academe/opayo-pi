<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Iso3166;

class States
{
    // Qualify by country, in case the state field gets extended to other
    // countries than the US.

    public static array $states = [
        'US' => [
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
        ],
    ];

    public static function hasStates(string $country): bool
    {
        return isset(static::$states[$country]);
    }

    /**
     * The API states that state codes must be an ISO3166-2 code. These codes
     * all start with the country code, e.g. "US-AL". The documentation states this
     * gateway supports the two-character codes only.
     */
    public static function isValid(string $country, string $code): bool
    {
        return isset(static::$states[$country][$code]);
    }

    /**
     * Optional country to return states for.
     */
    public static function getAll(?string $country_code = null): array
    {
        if (isset($country_code)) {
            return isset(static::$states[$country_code]) ? static::$states[$country_code] : [];
        }

        return static::$states;
    }
}
