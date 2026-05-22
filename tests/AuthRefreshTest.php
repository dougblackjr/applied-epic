<?php

declare(strict_types=1);

namespace Tns\Epic\Tests;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tns\Epic\AppliedEpic;
use Tns\Epic\Exceptions\ApiException;
use Tns\Epic\Tests\Support\MockHttpClient;

final class AuthRefreshTest extends TestCase
{
    public function test_a_401_refreshes_the_token_and_retries_the_request(): void
    {
        $apiCalls = 0;

        $http = new MockHttpClient(function (RequestInterface $request) use (&$apiCalls): ResponseInterface {
            $uri = (string) $request->getUri();

            if (str_contains($uri, '/auth/connect/token')) {
                return $this->jsonResponse(200, $this->fixture('token.json'));
            }

            ++$apiCalls;
            // The first API call is rejected as if the token had been revoked.
            if ($apiCalls === 1) {
                return $this->jsonResponse(401, '{"error":"invalid_token"}');
            }

            return $this->response(200, $this->fixture('account.json'));
        });

        $epic = AppliedEpic::make('consumer-key', 'consumer-secret', environment: 'mock', http: $http);

        $account = $epic->accounts->get('497f6eca-6276-4993-bfeb-53cbbbba6f08');

        self::assertSame('John Doe', $account['name']);
        self::assertSame(2, $apiCalls, 'the request should be retried once after the 401');
        self::assertSame(2, $http->countMatching('/auth/connect/token'), 'initial exchange + forced refresh');
    }

    public function test_a_persistent_401_surfaces_as_an_api_exception(): void
    {
        $http = new MockHttpClient(function (RequestInterface $request): ResponseInterface {
            if (str_contains((string) $request->getUri(), '/auth/connect/token')) {
                return $this->jsonResponse(200, $this->fixture('token.json'));
            }

            return $this->jsonResponse(401, '{"detail":"token rejected"}');
        });

        $epic = AppliedEpic::make('consumer-key', 'consumer-secret', environment: 'mock', http: $http);

        try {
            $epic->accounts->get('497f6eca-6276-4993-bfeb-53cbbbba6f08');
            self::fail('Expected an ApiException for a persistent 401.');
        } catch (ApiException $e) {
            self::assertSame(401, $e->statusCode);
            self::assertStringContainsString('token rejected', $e->getMessage());
        }
    }
}
