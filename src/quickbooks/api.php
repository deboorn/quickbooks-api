<?php

namespace QuickBooks;

/**
 * Quickbooks Exception
 *
 * @author Daniel Boorn
 */
class Exception extends \Exception
{
    //
}

/**
 * QuickBooks API
 *
 * A dead simple QuickBooks API library with zero dependencies.
 *
 * @author Daniel Boorn
 *
 */
class API
{
    /**
     *
     */
    const ENV_SANDBOX = 'sandbox';
    /**
     *
     */
    const ENV_PROD = 'production';

    /**
     * @var array|\string[][]
     */
    public static array $urls = [
        'sandbox'    => [
            'auth' => 'https://developer.api.intuit.com/.well-known/openid_sandbox_configuration',
            'api'  => 'https://sandbox-quickbooks.api.intuit.com',
        ],
        'production' => [
            'auth' => 'https://developer.api.intuit.com/.well-known/openid_configuration',
            'api'  => 'https://quickbooks.api.intuit.com',
        ],
    ];

    public static $basePath = '/v3/company';

    /**
     * @var string
     */
    public static string $env;
    /**
     * @var array
     */
    public static array $endpoints = [];

    /**
     * @var string
     */
    public string $scope = 'com.intuit.quickbooks.accounting com.intuit.quickbooks.payment openid profile email phone address';
    /**
     * @var string
     */
    public string $clientId;
    /**
     * @var string
     */
    public string $secret;
    /**
     * @var string|mixed|null
     */
    public $realmId;
    /**
     * @var mixed|null
     */
    public $token;

    /**
     * @param string $clientId
     * @param string $secret
     * @param $token
     * @param $realmId
     * @param string $env
     * @throws Exception
     */
    public function __construct(string $clientId, string $secret, $token = null, $realmId = null, string $env = self::ENV_SANDBOX)
    {
        static::$env = $env;
        static::setEndpoints($env);

        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->token = $token;
        $this->realmId = $realmId;
    }

    /**
     * @param string $clientId
     * @param string $secret
     * @param $token
     * @param $realmId
     * @param $env
     * @return API
     * @throws Exception
     */
    public static function forge(string $clientId, string $secret, $token = null, $realmId = null, string $env = self::ENV_SANDBOX)
    {
        return new self($clientId, $secret, $token, $realmId, $env);
    }

    /**
     * @param $env
     * @return mixed
     * @throws Exception
     */
    public static function setEndpoints($env = self::ENV_SANDBOX)
    {
        if (!empty(static::$endpoints[$env])) {
            return static::$endpoints[$env];
        }

        $buffer = @file_get_contents(static::$urls[static::$env]['auth']);
        $buffer = json_decode($buffer, true);
        if (empty($buffer['authorization_endpoint'])) {
            throw new Exception("Cannot Discover endpoints for QuickBooks ({$env})");
        }

        return static::$endpoints = $buffer;
    }

    /**
     * @param $token
     * @return void
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param string|null $refreshToken
     * @return mixed
     * @throws Exception
     */
    public function refreshToken(string $refreshToken = null)
    {
        $refreshToken = $refreshToken ?? $this->token['refresh_token'];

        $r = $this->fetchAuth(static::$endpoints['token_endpoint'], [
            "grant_type"    => "refresh_token",
            "refresh_token" => $refreshToken,
        ]);

        if (empty($r['access_token'])) {
            throw new Exception('Invalid Token Response: ' . print_r($r, true));
        }

        $this->token = $r;
        $this->created_at = time();
        return $this->token;
    }

    /**
     * @param $code
     * @param $redirectUri
     * @return mixed
     * @throws Exception
     */
    public function getTokenByCode($code, $redirectUri)
    {
        $r = $this->fetchAuth(static::$endpoints['token_endpoint'], [
            "grant_type"   => "authorization_code",
            "code"         => $code,
            "redirect_uri" => $redirectUri,
        ]);

        if (empty($r['access_token'])) {
            throw new Exception('Invalid Token Response: ' . print_r($r, true));
        }

        $this->token = $r;
        $this->token['created_at'] = time();

        return $this->token;
    }

    /**
     * @param $redirectUri
     * @param $state
     * @param $scope
     * @return void
     */
    function redirectAuthorization($redirectUri, $state = null, $scope = null)
    {
        $url = static::$endpoints['authorization_endpoint'];
        $params = array(
            'client_id'     => $this->clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $scope ?? $this->scope,
            'state'         => $state,
        );
        $url = sprintf("%s?%s", $url, http_build_query($params));

        header("Location: $url");
        \Response::redirect($url);
    }

    /**
     * @param $path
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function get($path, $params = null)
    {
        return $this->fetch((array)$params, $path, 'GET', true);
    }

    /**
     * @param $data
     * @param $path
     * @return mixed
     * @throws Exception
     */
    public function post($data, $path)
    {
        return $this->fetch(json_encode($data), $path, 'POST', true);
    }

    /**
     * @return mixed|string|null
     */
    public function getRealmId()
    {
        return $this->realmId;
    }

    /**
     * @param $realmId
     * @return void
     */
    public function setRealmId($realmId)
    {
        $this->realmId = $realmId;
    }

    /**
     * @param $params
     * @param $path
     * @param $verb
     * @param $authenticate
     * @return mixed
     * @throws Exception
     */
    public function fetch($params, $path, $verb = "GET", $authenticate = false)
    {
        $path = sprintf("%s/%s/%s", rtrim(static::$basePath,"/"), $this->realmId, ltrim($path,"/"));
        $data = is_array($params) ? http_build_query($params) : $params;

        $headers = ['Accept: application/json'];

        if ($verb !== 'GET') {
            $data = json_encode($params);
            $headers[] = 'Content-Type: application/json';
            $headers[] = "Content-Length: " . strlen($data);
        } else {
            $path .= "?{$data}";
        }

        if ($authenticate) {
            $headers[] = "Authorization: Bearer {$this->token['access_token']}";
        }

        $context = stream_context_create(array(
            'http' => array(
                'header'        => implode("\r\n", $headers) . "\r\n",
                'timeout'       => 60.0,
                'ignore_errors' => true,
                'method'        => $verb,
                'content'       => $data,
            ),
            'ssl'  => array(
                'verify_peer' => false,
            ),
        ));

        $apiUrl = static::$urls[static::$env]['api'] . $path;

        $r = @file_get_contents($apiUrl, false, $context);
        $r = json_decode($r, true);

        // some odd reason QB returned with partial caps sometimes
        if(!empty($r['Fault']['Error'])){
            $msg = "{$r['Fault']['Error'][0]['Message']}: {$r['Fault']['Error'][0]['Detail']}";
            throw new Exception($msg);
        }

        // other times, the error response is all lowercase
        if(!empty($r['fault']['error'])){
            $msg = "{$r['fault']['error'][0]['message']}: {$r['fault']['error'][0]['detail']}";
            throw new Exception($msg);
        }

        return $r;
    }

    /**
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return mixed
     */
    public function fetchAuth(string $url, array $params, array $headers = [])
    {

        $data = http_build_query($params);

        $headers = array_merge([
            sprintf("Authorization: Basic %s", base64_encode("{$this->clientId}:{$this->secret}")),
            'Content-Type: application/x-www-form-urlencoded',
            "Content-Length: " . strlen($data),
        ], $headers);

        $context = stream_context_create(array(
            'http' => array(
                'header'        => implode("\r\n", $headers) . "\r\n",
                'timeout'       => 60.0,
                'ignore_errors' => true,
                'method'        => 'POST',
                'content'       => $data,
            ),
            'ssl'  => array(
                'verify_peer' => false,
            ),
        ));

        $r = @file_get_contents($url, false, $context);
        $r = json_decode($r, true);
        return $r;
    }


}
