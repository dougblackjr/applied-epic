<?php
namespace Tns\Epic;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Tns\Epic\Http\BaseClient;
use Tns\Epic\Resources\{Lookups, Persons, Policies, Quotes, Logins};

final class Epic
{
    public Lookups $lookups;
    public Persons $persons;
    public Policies $policies;
    public Quotes $quotes;
    public Logins $logins;

    public function __construct(BaseClient $base)
    {
        $this->lookups = new Lookups($base);
        $this->persons = new Persons($base);
        $this->policies = new Policies($base);
        $this->quotes = new Quotes($base);
        $this->logins = new Logins($base);
    }

    /**
     * Static factory that wires PSR-18 client + PSR-17 factories and returns an Epic facade.
     */
    public static function make(
        string $baseUri,
        string $authKey,
        string $database,
        ?string $accept = 'application/json',
        ?HttpClient $http = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
        int $maxRetries = 3,
        int $initialDelayMs = 200
    ): self {
        $http = $http ?: Psr18ClientDiscovery::find();
        $requestFactory = $requestFactory ?: Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory = $streamFactory ?: Psr17FactoryDiscovery::findStreamFactory();

        $base = new BaseClient(
            http: $http,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            baseUri: $baseUri,
            authKey: $authKey,
            database: $database,
            accept: $accept,
            logger: $logger,
            maxRetries: $maxRetries,
            initialDelayMs: $initialDelayMs
        );

        return new self($base);
    }
}
