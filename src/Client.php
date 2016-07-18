<?php

/**
 * This file is part of VkClient package.
 *
 * @author ATehnix <atehnix@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ATehnix\VkClient;

use ATehnix\VkClient\Exceptions\VkException;
use ATehnix\VkClient\Requests\Request;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Client
 *
 * @package ATehnix\VkClient
 */
class Client
{
    const API_URI = 'https://api.vk.com/method/';
    const API_VERSION = '5.52';
    const API_TIMEOUT = 30.0;

    /**
     * @var ClientInterface
     */
    protected $http;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var bool
     */
    protected $passError = false;

    /**
     * Client constructor.
     *
     * @param string $version
     * @param ClientInterface $http
     */
    public function __construct($version = null, ClientInterface $http = null)
    {
        $this->version = $version ?: static::API_VERSION;
        $this->http = $http ?: new HttpClient([
            'base_uri'    => static::API_URI,
            'timeout'     => static::API_TIMEOUT,
            'http_errors' => false,
            'headers'     => [
                'User-Agent' => 'github.com/atehnix/vk-client',
                'Accept'     => 'application/json',
            ],
        ]);
    }

    /**
     * @param string $token
     * @return static
     */
    public function setDefaultToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @param bool $bool
     * @return static
     */
    public function setPassError($bool = true)
    {
        $this->passError = $bool;

        return $this;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function send(Request $request)
    {
        return $this->request(
            $request->getMethod(),
            $request->getParameters(),
            $request->getToken()
        );
    }

    /**
     * @param string $method
     * @param array $parameters
     * @param string|null $token
     * @return array
     */
    public function request($method, $parameters, $token = null)
    {
        $options = $this->buildOptions($parameters, $token);
        $response = $this->http->request('POST', $method, $options);

        return $this->getResponseData($response);
    }

    /**
     * @param array $parameters
     * @param string|null $requestToken
     * @return array
     */
    protected function buildOptions($parameters, $requestToken)
    {
        $parameters['v'] = $this->version;
        $token = $requestToken ?: $this->token;

        if ($token) {
            $parameters['access_token'] = $token;
        }

        return [RequestOptions::FORM_PARAMS => $parameters];
    }

    /**
     * @param ResponseInterface $response
     * @return array
     * @throws VkException
     */
    protected function getResponseData(ResponseInterface $response)
    {
        $data = json_decode((string)$response->getBody(), true);

        if (isset($data['error']) && !$this->passError) {
            throw $this->getException($data['error']);
        }

        return isset($data['response']) ? $data['response'] : $data;
    }

    /**
     * @param array $error
     * @return VkException
     */
    protected function getException($error)
    {
        $message = isset($error['error_msg']) ? $error['error_msg'] : '';
        $code = isset($error['error_code']) ? $error['error_code'] : 0;

        $map = [
            0  => Exceptions\VkException::class,
            1  => Exceptions\UnknownErrorVkException::class,
            5  => Exceptions\AuthorizationFailedVkException::class,
            6  => Exceptions\TooManyRequestsVkException::class,
            7  => Exceptions\PermissionDeniedVkException::class,
            9  => Exceptions\TooMuchSimilarVkException::class,
            10 => Exceptions\InternalErrorVkException::class,
            14 => Exceptions\CaptchaRequiredVkException::class,
            15 => Exceptions\AccessDeniedVkException::class,
        ];

        $exception = $map[$code] ?: $map[0];

        return new $exception($message, $code);
    }
}