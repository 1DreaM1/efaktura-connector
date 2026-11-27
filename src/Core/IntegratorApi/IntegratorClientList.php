<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** One page of OAuth clients and its optional continuation token. */
final class IntegratorClientList
{
    /** @var IntegratorClient[] */
    private array $clients;
    private ?PageToken $nextPageToken;

    /**
     * Creates one page of OAuth clients and its continuation token.
     *
     * @param  IntegratorClient[]    $clients       Client values in the provider page.
     * @param  PageToken|null        $nextPageToken Token for retrieving the next page, if present.
     * @throws InvalidInputException when clients contains a value of the wrong type.
     */
    public function __construct(array $clients, ?PageToken $nextPageToken)
    {
        $this->clients = TypedArray::valuesOfType(
            $clients,
            IntegratorClient::class,
            'Integrator client page items must be IntegratorClient values.'
        );
        $this->nextPageToken = $nextPageToken;
    }

    /** @return IntegratorClient[] */
    public function clients(): array
    {
        return $this->clients;
    }

    /** Returns the token for the next page, or null when this is the final page. */
    public function nextPageToken(): ?PageToken
    {
        return $this->nextPageToken;
    }
}
