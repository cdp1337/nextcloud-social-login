<?php

namespace OCA\SocialLogin\Provider;

use Hybridauth\Adapter\OAuth2;
use Hybridauth\Data;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\HttpClient\HttpClientInterface;
use Hybridauth\Logger\LoggerInterface;
use Hybridauth\Storage\StorageInterface;
use Hybridauth\User;

class CustomOAuth2 extends OAuth2
{

    public function __construct(
        $config = [],
        HttpClientInterface $httpClient = null,
        StorageInterface $storage = null,
        LoggerInterface $logger = null
    ) {
        parent::__construct($config, $httpClient, $storage, $logger);
        $this->providerId = $this->clientId;
    }

    /**
     * @return User\Profile
     * @throws UnexpectedApiResponseException
     * @throws \Hybridauth\Exception\HttpClientFailureException
     * @throws \Hybridauth\Exception\HttpRequestFailedException
     * @throws \Hybridauth\Exception\InvalidAccessTokenException
     */
    public function getUserProfile()
    {
        $profileFields = $this->strToArray($this->config->get('profile_fields'));
        $profileUrl = $this->config->get('endpoints')['profile_url'];

        if (count($profileFields) > 0) {
            $profileUrl .= (strpos($profileUrl, '?') !== false ? '&' : '?') . 'fields=' . implode(',', $profileFields);
        }

        $response = $this->apiRequest($profileUrl);
        if (isset($response->ocs->data)) {
            $response = $response->ocs->data;
        }
        if (!isset($response->identifier)) {
            $response->identifier = $response->id
                ?? $response->ID
                ?? $response->data->id
                ?? $response->user_id
                ?? $response->userid
                ?? $response->userId
                ?? $response->oauth_user_id
                ?? $response->sub
                ?? null
            ;
        }

        // Provide a map of common attributes to UserProfile keys
        // Mastodon for example uses 'avatar' for 'photoURL'
        $attributeMapping = [
            // User website, blog, web page
            'webSiteURL' => [
                'webSiteURL',
            ],
            // URL link to profile page on the IDp web site
            'profileURL' => [
                'profileURL',
                'url',
            ],
            // URL link to user photo or avatar
            'photoURL' => [
                'photoURL',
                'avatar',
            ],
            // User displayName provided by the IDp or a concatenation of first and last name.
            'displayName' => [
                $this->config->get('displayname_claim'),
                'displayName',
                'display_name',
                'username',
            ],
            // A short about_me
            'description' => [
                'description',
                'note',
            ],

            // Un-used, but available in the underlying OAuth logic
            /*
            // User's first name
            'firstName' => null,
            // User's last name
            'lastName' => null,
            // male or female
            'gender' => null,
            // Language
            'language' => null,
            // User age, we don't calculate it. we return it as is if the IDp provide it.
            'age' => null,
            // User birth Day
            'birthDay' => null,
            // User birth Month
            'birthMonth' => null,
            // User birth Year
            'birthYear' => null,
            // User email. Note: not all of IDp grant access to the user email
            'email' => null,
            // Verified user email. Note: not all of IDp grant access to verified user email
            'emailVerified' => null,
            // Phone number
            'phone' => null,
            // Complete user address
            'address' => null,
            // User country
            'country' => null,
            // Region
            'region' => null,
            // City
            'city' => null,
            // Postal code
            'zip' => null,
            */
        ];

        // Separate option for a pretty identifier
        // This can be problematic if multiple providers are allowed
        // but beneficial if only a single is used because it provides a human-friendly account
        // @todo Wrap this with a configurable parameter for "pretty" identifiers.
        $attributeMapping['identifier'] = ['acct'];

        $data = new Data\Collection($response);

        if (!$data->exists('identifier')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new User\Profile();
        foreach ($data->toArray() as $key => $value) {
            if ($key !== 'data') {
                // Resolve the remote key to the local mapped key
                $localKey = null;
                foreach($attributeMapping as $ak => $av) {
                    if (is_string($av) && $av == $key) {
                        $localKey = $ak;
                        break;
                    } elseif (is_array($av) && in_array($key, $av)) {
                        $localKey = $ak;
                        break;
                    }
                }

                if ($localKey !== null) {
                    $userProfile->$localKey = $value;
                }
            }
        }

        if (null !== $groups = $this->getGroups($data)) {
            $userProfile->data['groups'] = $groups;
        }
        if ($groupMapping = $this->config->get('group_mapping')) {
            $userProfile->data['group_mapping'] = $groupMapping;
        }

        return $userProfile;
    }

    protected function getGroups(Data\Collection $data)
    {
        if ($groupsClaim = $this->config->get('groups_claim')) {
            $nestedClaims = explode('.', $groupsClaim);
            $claim = array_shift($nestedClaims);
            $groups = $data->get($claim);
            while (count($nestedClaims) > 0) {
                $claim = array_shift($nestedClaims);
                if (!isset($groups->{$claim})) {
                    $groups = [];
                    break;
                }
                $groups = $groups->{$claim};
            }
            if (is_array($groups)) {
                $ret = [];
                foreach($groups as $g) {
                    $ret[] = $this->processGroupName($g);
                }
                return $ret;
            } elseif (is_string($groups)) {
                return $this->strToArray($groups);
            }
            return [];
        }
        return null;
    }

    /**
     * Process a group name to a flat string to be added to the list of groups
     *
     * Useful because Mastodon will return Roles as an array of stdClass objects with 'name' as the name of the group
     */
    private function processGroupName($value)
    {
        if (is_string($value)) {
            return $value;
        } elseif (is_object($value) && property_exists($value, 'name')) {
            return $value->name;
        } else {
            // what do?
            return $value;
        }
    }

    private function strToArray($str)
    {
        return array_filter(
            array_map('trim', explode(',', $str)),
            function ($val) { return $val !== ''; }
        );
    }
}
