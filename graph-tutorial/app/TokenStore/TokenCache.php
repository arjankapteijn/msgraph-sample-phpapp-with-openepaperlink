<?php

namespace App\TokenStore;

use Illuminate\Support\Facades\Cache;

class TokenCache {
  public function storeTokens($accessToken, $user) {
    Cache::put('accessToken', $accessToken->getToken());
    Cache::put('refreshToken', $accessToken->getRefreshToken());
    Cache::put('tokenExpires', $accessToken->getExpires());
    Cache::put('userName', $user->getDisplayName());
    Cache::put('userEmail', null !== $user->getMail() ? $user->getMail() : $user->getUserPrincipalName());
    Cache::put('userTimeZone', $user->getMailboxSettings()->getTimeZone());
  }

  public function clearTokens() {
    Cache::forget('accessToken');
    Cache::forget('refreshToken');
    Cache::forget('tokenExpires');
    Cache::forget('userName');
    Cache::forget('userEmail');
    Cache::forget('userTimeZone');
  }

    // <GetAccessTokenSnippet>
    public function getAccessToken() {
      // Check if tokens exist
      if (!Cache::has('accessToken') ||
        !Cache::has('refreshToken') ||
        !Cache::has('tokenExpires')) {
        return '';
      }
  
      // Check if token is expired
      //Get current time + 5 minutes (to allow for time differences)
      $now = time() + 300;
      if (Cache::get('tokenExpires') <= $now) {
        // Token is expired (or very close to it)
        // so let's refresh
  
        // Initialize the OAuth client
        $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
          'clientId'                => config('azure.appId'),
          'clientSecret'            => config('azure.appSecret'),
          'redirectUri'             => config('azure.redirectUri'),
          'urlAuthorize'            => config('azure.authority').config('azure.authorizeEndpoint'),
          'urlAccessToken'          => config('azure.authority').config('azure.tokenEndpoint'),
          'urlResourceOwnerDetails' => '',
          'scopes'                  => config('azure.scopes')
        ]);
  
        try {
          $newToken = $oauthClient->getAccessToken('refresh_token', [
            'refresh_token' => Cache::get('refreshToken')
          ]);
  
          // Store the new values
          $this->updateTokens($newToken);
  
          return $newToken->getToken();
        }
        catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
          return '';
        }
      }
  
      // Token is still valid, just return it
      return Cache::get('accessToken');
    }
    // </GetAccessTokenSnippet>

  // <UpdateTokensSnippet>
  public function updateTokens($accessToken) {
    Cache::put('accessToken', $accessToken->getToken());
    Cache::put('refreshToken', $accessToken->getRefreshToken());
    Cache::put('tokenExpires', $accessToken->getExpires());
  }
  // </UpdateTokensSnippet>
}