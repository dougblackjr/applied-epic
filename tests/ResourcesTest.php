<?php

declare(strict_types=1);

namespace Tns\Epic\Tests;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tns\Epic\AppliedEpic;
use Tns\Epic\Exceptions\AppliedEpicException;
use Tns\Epic\Tests\Support\MockHttpClient;

final class ResourcesTest extends TestCase
{
    public function test_get_resolves_a_single_record_by_id(): void
    {
        $http = $this->routedClient([
            '/epic/account/v1/accounts/' => fn () => $this->response(200, $this->fixture('account.json')),
        ]);

        $epic = AppliedEpic::make('k', 's', environment: 'mock', http: $http);
        $account = $epic->accounts->get('497f6eca-6276-4993-bfeb-53cbbbba6f08');

        self::assertSame('497f6eca-6276-4993-bfeb-53cbbbba6f08', $account['id']);
        self::assertSame('CLIENT', $account['type']);
        self::assertSame(1, $http->countMatching('/accounts/497f6eca-6276-4993-bfeb-53cbbbba6f08'));
    }

    public function test_attachment_download_streams_the_file_behind_the_record(): void
    {
        $http = $this->routedClient([
            '/epic/attachment/v2/attachments/' => fn () => $this->response(200, $this->fixture('attachment.json')),
            'documentservice.appliedsystems.com' => fn () => $this->response(200, '%PDF-1.7 mock bytes', 'application/pdf'),
        ]);

        $epic = AppliedEpic::make('k', 's', environment: 'mock', http: $http);
        $stream = $epic->attachments->download('9fd6f78c-eb5f-44a1-a9df-dc6179d474bd');

        self::assertSame('%PDF-1.7 mock bytes', (string) $stream);
        self::assertSame(1, $http->countMatching('documentservice.appliedsystems.com/documents/v1/accident-report.pdf'));
    }

    public function test_activities_list_requires_a_client_filter(): void
    {
        $epic = AppliedEpic::make('k', 's', environment: 'mock', http: $this->routedClient([]));

        $this->expectException(AppliedEpicException::class);
        $this->expectExceptionMessage("requires a 'client' filter");

        // list() returns a generator; force evaluation so the guard runs.
        iterator_to_array($epic->activities->list([]));
    }

    public function test_array_and_boolean_filters_are_encoded_for_the_applied_api(): void
    {
        $http = $this->routedClient([
            '/epic/policy/v2/policies' => fn () => $this->response(200, $this->fixture('policies-page-2.json')),
        ]);

        $epic = AppliedEpic::make('k', 's', environment: 'mock', http: $http);
        iterator_to_array($epic->policies->list([
            'limit' => 50,
            'historic_status' => ['CURRENT', 'HISTORIC'],
            'includeInactive' => false,
        ]), false);

        $uri = (string) $http->requests[1]->getUri();
        // Multi-values are comma-joined (OpenAPI form/explode:false), not param[]=.
        self::assertStringContainsString('historic_status=CURRENT%2CHISTORIC', $uri);
        self::assertStringContainsString('includeInactive=false', $uri);
    }

    /**
     * Build a mock client that dispatches by URI substring. The OAuth token
     * endpoint is always wired so make() can authenticate.
     *
     * @param array<string, callable(): ResponseInterface> $routes
     */
    private function routedClient(array $routes): MockHttpClient
    {
        return new MockHttpClient(function (RequestInterface $request) use ($routes): ResponseInterface {
            $uri = (string) $request->getUri();

            if (str_contains($uri, '/auth/connect/token')) {
                return $this->jsonResponse(200, $this->fixture('token.json'));
            }

            foreach ($routes as $needle => $responder) {
                if (str_contains($uri, $needle)) {
                    return $responder();
                }
            }

            return $this->response(404, '{"detail":"no route for ' . $uri . '"}');
        });
    }
}
