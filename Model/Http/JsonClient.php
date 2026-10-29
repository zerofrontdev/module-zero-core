<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Http;

use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * One JSON request over cURL, for the modules that talk to a payment or
 * delivery API: the timeout, the JSON body going out and the JSON answer
 * coming back are decided here, once. What stays with each module: the URL,
 * the authorization header, what an error answer means and what to tell
 * the customer.
 */
class JsonClient
{
    public function __construct(private readonly CurlFactory $curlFactory)
    {
    }

    /**
     * @param array<string, string> $headers
     * @param bool $emptyBodyAsObject send an empty or missing body of a POST/PUT/PATCH as {} instead of null or []
     * @return array{status: int, body: ?array} body is null when the answer is not a JSON object or list
     * @throws \Throwable when the request itself fails (no connection, timeout)
     */
    public function request(string $method, string $url, ?array $body = null, array $headers = [], int $timeout = 15, bool $emptyBodyAsObject = false): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout($timeout);
        $curl->addHeader('Accept', 'application/json');
        foreach ($headers as $name => $value) {
            $curl->addHeader($name, $value);
        }
        $method = strtoupper($method);
        if ($body !== null || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            if (!array_key_exists('content-type', array_change_key_case($headers))) {
                $curl->addHeader('Content-Type', 'application/json');
            }
            if ($method !== 'POST') {
                $curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
            }
            $curl->post($url, (string) json_encode($emptyBodyAsObject && !$body ? new \stdClass() : $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            if ($method !== 'GET') {
                $curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
            }
            $curl->get($url);
        }
        $answer = json_decode((string) $curl->getBody(), true);

        return ['status' => (int) $curl->getStatus(), 'body' => is_array($answer) ? $answer : null];
    }
}
