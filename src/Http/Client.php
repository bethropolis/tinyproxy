<?php

declare(strict_types=1);

namespace TinyProxy\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use TinyProxy\Config\Configuration;
use TinyProxy\Exception\HttpException;
use TinyProxy\Logger\LoggerInterface;
use TinyProxy\Security\UrlValidator;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP Client wrapper with security checks
 */
class Client
{
    private GuzzleClient $guzzle;

    public function __construct(
        private readonly UrlValidator $validator,
        private readonly Configuration $config,
        private readonly LoggerInterface $logger
    ) {
        $this->guzzle = new GuzzleClient([
            'timeout' => $config->getInt('http.timeout', 30),
            'verify' => $config->getBool('http.verify_ssl', true),
            'allow_redirects' => [
                'max' => $config->getInt('http.max_redirects', 5),
                'strict' => true,
                'track_redirects' => true
            ],
        ]);
    }

    /**
     * Make GET request with security validation
     */
    public function get(string $url, array $options = []): Response
    {
        // Validate URL for SSRF
        $this->validator->validate($url);

        // Merge default headers
        $options['headers'] = array_merge([
            'User-Agent' => $this->config->getString('http.user_agent', 'TinyProxy/2.0'),
            'Referer' => $url,
        ], $options['headers'] ?? []);

        try {
            $response = $this->guzzle->get($url, $options);
            return new Response($response);
        } catch (RequestException $e) {
            $this->logger->error('HTTP request failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            throw new HttpException(
                'Request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Make POST request with security validation
     */
    public function post(string $url, array $data = [], array $options = []): Response
    {
        $this->validator->validate($url);

        $options['headers'] = array_merge([
            'User-Agent' => $this->config->getString('PROXY_USER_AGENT'),
        ], $options['headers'] ?? []);

        $options['form_params'] = $data;

        try {
            $response = $this->guzzle->post($url, $options);
            return new Response($response);
        } catch (RequestException $e) {
            $this->logger->error('HTTP POST failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            throw new HttpException(
                'POST request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Make HEAD request
     */
    public function head(string $url, array $options = []): Response
    {
        $this->validator->validate($url);

        try {
            $response = $this->guzzle->head($url, $options);
            return new Response($response);
        } catch (RequestException $e) {
            throw new HttpException(
                'HEAD request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
