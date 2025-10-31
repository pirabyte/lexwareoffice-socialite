<?php

namespace Pirabyte\Socialite\LexwareOffice;

use GuzzleHttp\RequestOptions;
use Random\RandomException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    const IDENTIFIER = 'lexwareoffice';

    /**
     * {@inheritdoc}
     */
    protected $scopes = [''];

    /**
     * Store the custom base URL when set via setConfig
     * 
     * @var string|null
     */
    protected $customBaseUrl = null;

    /**
     * Get the base URL from config or additional config options
     * 
     * @return string
     */
    protected function getBaseUrl(): string
    {
        // If custom URL was set via setConfig, use it
        if ($this->customBaseUrl !== null) {
            return $this->customBaseUrl;
        }
        
        // Fallback to config file
        return \config('services.lexwareoffice.url', '');
    }

    /**
     * Override setConfig to capture additional config options
     * 
     * @param \SocialiteProviders\Manager\Config $config
     * @return $this
     */
    public function setConfig($config)
    {
        parent::setConfig($config);
        
        // Extract URL from additional config options using reflection
        // The Config object stores additional config as a protected property
        $reflection = new \ReflectionClass($config);
        if ($reflection->hasProperty('additionalConfig')) {
            $property = $reflection->getProperty('additionalConfig');
            $property->setAccessible(true);
            $additionalConfig = $property->getValue($config);
            
            if (is_array($additionalConfig) && isset($additionalConfig['url'])) {
                $this->customBaseUrl = $additionalConfig['url'];
            }
        }
        
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->getBaseUrl() . '/oauth2/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->getBaseUrl() . '/oauth2/token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);
        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Überschreiben, um PKCE-Parameter beim Authorisierungs-Request hinzuzufügen.
     */
    protected function getCodeFields($state = null): array
    {
        $fields = parent::getCodeFields($state);

        // Erzeugen Sie einen zufälligen code_verifier
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // Speichern Sie den code_verifier, um ihn später im Token Request zu verwenden
        session(['oauth.lexoffice.code_verifier' => $codeVerifier]);

        $fields['code_challenge'] = $codeChallenge;
        $fields['code_challenge_method'] = 'S256';

        return $fields;
    }

    /**
     * Erzeugt ein zufälliges String als Code Verifier.
     * @throws RandomException
     */
    protected function generateCodeVerifier(): string
    {
        // Ein Beispiel: 128 zufällige Zeichen (kann je nach Anforderungen angepasst werden)
        return bin2hex(random_bytes(64));
    }

    /**
     * Berechnet die Code Challenge aus dem Code Verifier.
     */
    protected function generateCodeChallenge($codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    public function user(): ?User
    {
        // Tauschen Sie den Code gegen Token aus
        $tokenResponse = $this->getAccessTokenResponse(request('code'));

        // Erstellen Sie ein neues User-Objekt, das die Token-Daten enthält.
        return (new User)->setRaw($tokenResponse)->map([
            'token'        => $tokenResponse['access_token'] ?? null,
            'refreshToken' => $tokenResponse['refresh_token'] ?? null,
            'expiresIn'    => $tokenResponse['expires_in'] ?? null,
        ]);
    }

    protected  function getTokenFields($code): array
    {
        $fields = parent::getTokenFields($code);

        // Abrufen des zuvor gespeicherten code_verifier
        $codeVerifier = session('oauth.lexoffice.code_verifier');
        $fields['code_verifier'] = $codeVerifier;

        return $fields;
    }

    public function getAccessTokenResponse($code) : array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            \GuzzleHttp\RequestOptions::HEADERS => [
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                'Accept'        => 'application/json',
            ],
            \GuzzleHttp\RequestOptions::FORM_PARAMS => $this->getTokenFields($code),
        ]);
        $value = json_decode((string) $response->getBody(), true);
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user): \Laravel\Socialite\Two\User|User
    {
        return (new User)->setRaw($user)->map([
            'id'       => $user['id'],
            'nickname' => $user['username'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'avatar'   => $user['avatar'],
        ]);
    }
}
