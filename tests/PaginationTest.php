<?php

declare(strict_types=1);

namespace Tns\Epic\Tests;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tns\Epic\AppliedEpic;
use Tns\Epic\Tests\Support\MockHttpClient;

/**
 * The definition-of-done case: AppliedEpic::make(...)->policies->list(['limit' => 50])
 * returns mapped policy records, paginating transparently, with the bearer
 * token acquired and cached automatically.
 */
final class PaginationTest extends TestCase
{
    public function test_list_walks_every_page_and_yields_all_records(): void
    {
        $http = new MockHttpClient(function (RequestInterface $request): ResponseInterface {
            $uri = (string) $request->getUri();

            if (str_contains($uri, '/auth/connect/token')) {
                return $this->jsonResponse(200, $this->fixture('token.json'));
            }

            if (str_contains($uri, '/epic/policy/v2/policies')) {
                parse_str($request->getUri()->getQuery(), $query);
                $page = ((int) ($query['offset'] ?? 0)) >= 2 ? 'policies-page-2.json' : 'policies-page-1.json';

                return $this->response(200, $this->fixture($page));
            }

            return $this->response(404, '{}');
        });

        $epic = AppliedEpic::make('consumer-key', 'consumer-secret', environment: 'mock', http: $http);

        $policies = iterator_to_array($epic->policies->list(['limit' => 2]), false);

        // Two pages (2 + 1) flattened into one transparent stream of records.
        self::assertCount(3, $policies);
        self::assertSame('Acme Corp - General Liability', $policies[0]['description']);
        self::assertSame('Acme Corp - Workers Compensation', $policies[2]['description']);
        self::assertSame('GL-100245', $policies[0]['policyNumber']);

        // Exactly two page requests were issued, with the requested page size.
        self::assertSame(2, $http->countMatching('/epic/policy/v2/policies'));
        self::assertSame(1, $http->countMatching('limit=2&offset=0'));
        self::assertSame(1, $http->countMatching('limit=2&offset=2'));
    }

    public function test_token_is_acquired_once_and_reused_across_pages(): void
    {
        $http = new MockHttpClient(function (RequestInterface $request): ResponseInterface {
            $uri = (string) $request->getUri();

            if (str_contains($uri, '/auth/connect/token')) {
                return $this->jsonResponse(200, $this->fixture('token.json'));
            }

            parse_str($request->getUri()->getQuery(), $query);
            $page = ((int) ($query['offset'] ?? 0)) >= 2 ? 'policies-page-2.json' : 'policies-page-1.json';

            return $this->response(200, $this->fixture($page));
        });

        $epic = AppliedEpic::make('consumer-key', 'consumer-secret', environment: 'mock', http: $http);

        iterator_to_array($epic->policies->list(['limit' => 2]), false);

        // One token exchange covers both page requests.
        self::assertSame(1, $http->countMatching('/auth/connect/token'));

        // Every API request carried the cached bearer token.
        foreach ($http->requests as $request) {
            if (str_contains((string) $request->getUri(), '/policies')) {
                self::assertSame(
                    'Bearer mock-bearer-token-001',
                    $request->getHeaderLine('Authorization'),
                );
            }
        }
    }
}
