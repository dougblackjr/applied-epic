<?php

declare(strict_types=1);

namespace Tns\Epic;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Tns\Epic\Resources\Accounts;
use Tns\Epic\Resources\Activities;
use Tns\Epic\Resources\Attachments;
use Tns\Epic\Resources\Opportunities;
use Tns\Epic\Resources\Policies;

/**
 * Entry point for the modern Applied API client.
 *
 *     $epic = AppliedEpic::make($consumerKey, $consumerSecret, environment: 'mock');
 *
 *     foreach ($epic->policies->list(['limit' => 50]) as $policy) {
 *         // $policy is a plain associative array decoded from HAL+JSON
 *     }
 *
 * The bearer token is acquired and cached automatically; pagination across
 * `_embedded` collections is transparent.
 */
final class AppliedEpic
{
    /**
     * Default OAuth scopes requested for the client-credentials grant — the
     * read scope of every resource this client exposes.
     *
     * @var list<string>
     */
    public const DEFAULT_SCOPES = [
        'epic/accounts:read',
        'epic/policies:read',
        'epic/opportunities:read',
        'epic/activity:read',
        'epic/attachments:read',
    ];

    public readonly Accounts $accounts;
    public readonly Policies $policies;
    public readonly Opportunities $opportunities;
    public readonly Activities $activities;
    public readonly Attachments $attachments;

    public function __construct(public readonly Client $client)
    {
        $this->accounts = new Accounts($client);
        $this->policies = new Policies($client);
        $this->opportunities = new Opportunities($client);
        $this->activities = new Activities($client);
        $this->attachments = new Attachments($client);
    }

    /**
     * Build a fully wired client from a Consumer Key + Secret.
     *
     * The PSR-18 HTTP client and PSR-17 factories are discovered automatically
     * unless supplied — inject a mock $http to exercise the client in tests.
     *
     * @param string|Environment $environment 'production' | 'mock', or a custom Environment.
     * @param list<string>       $scopes      OAuth scopes; defaults to {@see DEFAULT_SCOPES}.
     */
    public static function make(
        string $consumerKey,
        string $consumerSecret,
        string|Environment $environment = 'production',
        array $scopes = [],
        ?HttpClient $http = null,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $env = Environment::resolve($environment);

        $http ??= Psr18ClientDiscovery::find();
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();

        $tokenProvider = new OAuthTokenProvider(
            http: $http,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            tokenUrl: $env->tokenUrl,
            consumerKey: $consumerKey,
            consumerSecret: $consumerSecret,
            scopes: $scopes === [] ? self::DEFAULT_SCOPES : $scopes,
            cache: $cache,
            logger: $logger,
        );

        $client = new Client(
            http: $http,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            tokenProvider: $tokenProvider,
            apiBaseUri: $env->apiBaseUri,
            logger: $logger,
        );

        return new self($client);
    }
}
