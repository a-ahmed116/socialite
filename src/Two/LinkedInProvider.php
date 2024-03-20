<?php

namespace Laravel\Socialite\Two;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;

class LinkedInProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['profile', 'emailaddress'];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://www.linkedin.com/oauth/v2/authorization', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        return $this->getBasicProfile($token);
    }

    /**
     * Get the basic profile fields for the user.
     *
     * @param  string  $token
     * @return array
     */
    protected function getBasicProfile($token)
    {
        $fields = ['picture', 'given_name', 'family_name', 'email', 'sub'];

        if (in_array('profile', $this->getScopes())) {
            array_push($fields, 'vanityName');
        }

        $response = $this->getHttpClient()->get('https://api.linkedin.com/v2/userinfo', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
            RequestOptions::QUERY => [
                'projection' => '('.implode(',', $fields).')',
            ],
        ]);

        return (array) json_decode($response->getBody(), true);
    }

    /**
     * Get the email address for the user.
     *
     * @param  string  $token
     * @return array
     */
    protected function getEmailAddress($token)
    {
        $response = $this->getHttpClient()->get('https://api.linkedin.com/v2/emailAddress', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
            RequestOptions::QUERY => [
                'q' => 'members',
                'projection' => '(elements*(handle~))',
            ],
        ]);

        return (array) Arr::get((array) json_decode($response->getBody(), true), 'elements.0.handle~');
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        $firstName = Arr::get($user, 'given_name');
        $lastName = Arr::get($user, 'family_name');
        $avatar = Arr::get($user, 'picture');

        return (new User)->setRaw($user)->map([
            'id' => $user['sub'],
            'nickname' => null,
            'name' => $firstName.' '.$lastName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => Arr::get($user, 'email'),
            'avatar' => $avatar,
        ]);
    }
}
