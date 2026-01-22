<?php
/**
 * Copyright 2025 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace Amazon\CreatorsAPI\v1\com\amazon\creators\auth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * OAuth2TokenManager Class 
 * Manages OAuth2 token lifecycle including acquisition, caching, and refresh
 */
class OAuth2TokenManager
{
    /**
     * OAuth2 configuration
     *
     * @var OAuth2Config
     */
    private $config;

    /**
     * Current access token
     *
     * @var string|null
     */
    private $accessToken;

    /**
     * Token expiration timestamp
     *
     * @var int|null
     */
    private $expiresAt;

    /**
     * HTTP client for token requests
     *
     * @var Client
     */
    private $httpClient;

    /**
     * Constructor
     *
     * @param OAuth2Config $config OAuth2 configuration
     */
    public function __construct(OAuth2Config $config)
    {
        $this->config = $config;
        $this->httpClient = new Client();
        $this->accessToken = null;
        $this->expiresAt = null;
    }

    /**
     * Gets a valid OAuth2 access token, refreshing if necessary
     *
     * @return string A valid access token
     * @throws \Exception If token acquisition fails
     */
    public function getToken()
    {
        // First, try to get token from Laravel cache if available
        if ($this->isLaravelCacheAvailable()) {
            $cachedData = $this->getTokenFromLaravelCache();
            if ($cachedData !== null && isset($cachedData['token']) && isset($cachedData['expiresAt'])) {
                $cachedExpiresAt = (int)$cachedData['expiresAt'];
                if (time() < $cachedExpiresAt) {
                    // Valid token from Laravel cache, update in-memory cache
                    $this->accessToken = $cachedData['token'];
                    $this->expiresAt = $cachedExpiresAt;
                    return $this->accessToken;
                }
            }
        }

        // Check in-memory cache
        if ($this->isTokenValid()) {
            // If in-memory cache is valid, optionally update Laravel cache
            if ($this->isLaravelCacheAvailable()) {
                $this->storeTokenInLaravelCache($this->accessToken, $this->expiresAt);
            }
            return $this->accessToken;
        }

        // No valid token found, refresh it
        return $this->refreshToken();
    }

    /**
     * Checks if the current token is valid and not expired
     *
     * @return bool True if the token is valid, false otherwise
     */
    public function isTokenValid()
    {
        return $this->accessToken !== null && 
               $this->expiresAt !== null && 
               time() < $this->expiresAt;
    }

    /**
     * Refreshes the OAuth2 access token using client credentials grant
     *
     * @return string The new access token
     * @throws \Exception If token refresh fails
     */
    private function refreshToken()
    {
        try {
            $response = $this->httpClient->post($this->config->getTokenEndpoint(), [
                'form_params' => [
                    'grant_type' => $this->config->getGrantType(),
                    'client_id' => $this->config->getClientId(),
                    'client_secret' => $this->config->getClientSecret(),
                    'scope' => $this->config->getScope()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (!isset($data['access_token'])) {
                throw new \Exception('No access token received from OAuth2 endpoint');
            }

            $this->accessToken = $data['access_token'];
            
            // Set expiration time with a 30-second buffer to avoid edge cases
            $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
            $this->expiresAt = time() + $expiresIn - 30;

            // Store token in Laravel cache if available
            if ($this->isLaravelCacheAvailable()) {
                $this->storeTokenInLaravelCache($this->accessToken, $this->expiresAt);
            }

            return $this->accessToken;

        } catch (RequestException $e) {
            // Clear existing token on failure
            $this->clearToken();
            
            $errorMessage = 'Failed to refresh OAuth2 token: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $errorMessage .= ' Response: ' . $errorBody;
            }
            
            throw new \Exception($errorMessage, $e->getCode(), $e);
            
        } catch (\Exception $e) {
            // Clear existing token on failure
            $this->clearToken();
            
            throw new \Exception('Failed to refresh OAuth2 token: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Clears the cached token, forcing a refresh on the next getToken() call
     *
     * @return void
     */
    public function clearToken()
    {
        $this->accessToken = null;
        $this->expiresAt = null;
        
        // Also clear Laravel cache if available
        if ($this->isLaravelCacheAvailable()) {
            $this->clearLaravelCache();
        }
    }

    /**
     * Gets token information for debugging
     *
     * @return array Token information
     */
    public function getTokenInfo()
    {
        return [
            'hasToken' => $this->accessToken !== null,
            'isValid' => $this->isTokenValid(),
            'expiresAt' => $this->expiresAt,
            'expiresIn' => $this->expiresAt ? max(0, $this->expiresAt - time()) : 0,
            'laravelCacheAvailable' => $this->isLaravelCacheAvailable()
        ];
    }

    /**
     * Checks if Laravel Cache facade is available
     *
     * @return bool True if Laravel Cache is available, false otherwise
     */
    private function isLaravelCacheAvailable(): bool
    {
        // Check if Laravel Cache facade class exists
        if (class_exists('Illuminate\Support\Facades\Cache')) {
            try {
                // Try to resolve the Cache facade through Laravel's app container
                if (function_exists('app')) {
                    $cache = app('cache');
                    return $cache !== null;
                }
                // Fallback: check if Cache facade can be accessed statically
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
        
        return false;
    }

    /**
     * Generates a unique cache key based on OAuth2 configuration
     *
     * @return string Cache key
     */
    private function generateCacheKey(): string
    {
        $clientId = $this->config->getClientId();
        $version = $this->config->getVersion();
        $tokenEndpoint = $this->config->getTokenEndpoint();
        $scope = $this->config->getScope();
        
        // Create a unique hash based on credentials
        $hash = md5($clientId . '|' . $version . '|' . $tokenEndpoint . '|' . $scope);
        
        return 'creatorsapi:oauth2:token:' . $hash;
    }

    /**
     * Retrieves token from Laravel cache
     *
     * @return array|null Token data array with 'token' and 'expiresAt' keys, or null if not found
     */
    private function getTokenFromLaravelCache(): ?array
    {
        if (!$this->isLaravelCacheAvailable()) {
            return null;
        }

        try {
            $cacheKey = $this->generateCacheKey();
            
            // Try to get cache using Laravel's Cache facade
            if (class_exists('Illuminate\Support\Facades\Cache')) {
                $cache = \Illuminate\Support\Facades\Cache::get($cacheKey);
                if ($cache !== null && is_array($cache)) {
                    return $cache;
                }
            }
        } catch (\Exception $e) {
            // Silently fail and fall back to in-memory cache
            return null;
        }

        return null;
    }

    /**
     * Stores token in Laravel cache
     *
     * @param string $token Access token
     * @param int $expiresAt Expiration timestamp
     * @return void
     */
    private function storeTokenInLaravelCache(string $token, int $expiresAt): void
    {
        if (!$this->isLaravelCacheAvailable()) {
            return;
        }

        try {
            $cacheKey = $this->generateCacheKey();
            $cacheData = [
                'token' => $token,
                'expiresAt' => $expiresAt
            ];
            
            // Calculate TTL in seconds (with buffer)
            $ttl = max(0, $expiresAt - time());
            
            // Store in Laravel cache with TTL
            if (class_exists('Illuminate\Support\Facades\Cache')) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $cacheData, $ttl);
            }
        } catch (\Exception $e) {
            // Silently fail - in-memory cache will still work
        }
    }

    /**
     * Clears token from Laravel cache
     *
     * @return void
     */
    private function clearLaravelCache(): void
    {
        if (!$this->isLaravelCacheAvailable()) {
            return;
        }

        try {
            $cacheKey = $this->generateCacheKey();
            
            if (class_exists('Illuminate\Support\Facades\Cache')) {
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
