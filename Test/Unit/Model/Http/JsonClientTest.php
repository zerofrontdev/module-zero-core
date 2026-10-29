<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Model\Http;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Http\JsonClient;

class JsonClientTest extends TestCase
{
    private Curl&MockObject $curl;
    private JsonClient $client;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $factory = $this->createMock(CurlFactory::class);
        $factory->method('create')->willReturn($this->curl);
        $this->client = new JsonClient($factory);
    }

    public function testAPostSendsTheBodyAsJsonWithTheCallersHeadersAndTimeout(): void
    {
        $headers = [];
        $this->curl->method('addHeader')->willReturnCallback(function (string $name, string $value) use (&$headers): void {
            $headers[$name] = $value;
        });
        $this->curl->expects(self::once())->method('setTimeout')->with(7);
        $this->curl->expects(self::once())->method('post')->with('https://api.example/pay', '{"url":"https://a/b","n":1}');
        $this->curl->method('getStatus')->willReturn(201);
        $this->curl->method('getBody')->willReturn('{"id":"tr_1"}');

        $answer = $this->client->request('POST', 'https://api.example/pay', ['url' => 'https://a/b', 'n' => 1], ['Authorization' => 'Bearer k'], 7);

        self::assertSame(['status' => 201, 'body' => ['id' => 'tr_1']], $answer);
        self::assertSame(['Accept' => 'application/json', 'Authorization' => 'Bearer k', 'Content-Type' => 'application/json'], $headers);
    }

    public function testAGetHasNoBodyAndNoContentType(): void
    {
        $headers = [];
        $this->curl->method('addHeader')->willReturnCallback(function (string $name, string $value) use (&$headers): void {
            $headers[$name] = $value;
        });
        $this->curl->expects(self::once())->method('get')->with('https://api.example/pay/1');
        $this->curl->expects(self::never())->method('post');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"status":"paid"}');

        $answer = $this->client->request('GET', 'https://api.example/pay/1');

        self::assertSame('paid', $answer['body']['status']);
        self::assertArrayNotHasKey('Content-Type', $headers);
    }

    public function testADeleteIsAGetWithTheMethodSwitched(): void
    {
        $this->curl->expects(self::once())->method('setOption')->with(CURLOPT_CUSTOMREQUEST, 'DELETE');
        $this->curl->expects(self::once())->method('get');
        $this->curl->method('getStatus')->willReturn(204);
        $this->curl->method('getBody')->willReturn('');

        self::assertSame(['status' => 204, 'body' => null], $this->client->request('DELETE', 'https://api.example/pay/1'));
    }

    public function testAnAnswerThatIsNotJsonComesBackAsNullBody(): void
    {
        $this->curl->method('getStatus')->willReturn(502);
        $this->curl->method('getBody')->willReturn('<html>Bad gateway</html>');

        self::assertSame(['status' => 502, 'body' => null], $this->client->request('GET', 'https://api.example/x'));
    }

    public function testATransportFailureIsNotSwallowed(): void
    {
        $this->curl->method('get')->willThrowException(new \RuntimeException('timeout'));

        $this->expectException(\RuntimeException::class);
        $this->client->request('GET', 'https://api.example/x');
    }

    public function testAnEmptyBodyGoesOutAsAnObjectOnlyWhenAsked(): void
    {
        $this->curl->expects(self::exactly(3))->method('post')->willReturnCallback(function (string $url, string $body): void {
            static $seen = [];
            $seen[] = $body;
            if (count($seen) === 3) {
                self::assertSame(['null', '{}', '{}'], $seen);
            }
        });

        $this->client->request('POST', 'https://api.example/a');
        $this->client->request('POST', 'https://api.example/a', null, [], 15, true);
        $this->client->request('POST', 'https://api.example/a', [], [], 15, true);
    }

    public function testAContentTypeOfTheCallerReplacesTheDefaultOne(): void
    {
        $headers = [];
        $this->curl->method('addHeader')->willReturnCallback(function (string $name, string $value) use (&$headers): void {
            $headers[$name] = $value;
        });
        $this->curl->method('getBody')->willReturn('{}');

        $this->client->request('POST', 'https://api.example/a', ['a' => 1], ['content-type' => 'application/json; charset=UTF-8']);

        self::assertSame('application/json; charset=UTF-8', $headers['content-type']);
        self::assertArrayNotHasKey('Content-Type', $headers);
    }
}
