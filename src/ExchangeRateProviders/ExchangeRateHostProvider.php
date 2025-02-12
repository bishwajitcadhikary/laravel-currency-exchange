<?php

declare(strict_types=1);

namespace Worksome\Exchange\ExchangeRateProviders;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Worksome\Exchange\Contracts\ExchangeRateProvider;
use Worksome\Exchange\Support\Rates;

final class ExchangeRateHostProvider implements ExchangeRateProvider
{
    public function __construct(
        private Factory $client,
        private string $accessKey,
        private string $baseUrl = 'https://api.exchangerate.host',
    ) {
    }

    /**
     * @throws RequestException|ConnectionException
     */
    public function getRates(string $baseCurrency, array $currencies): Rates
    {
        $data = $this->makeRequest($baseCurrency, $currencies);

        $formattedQuotes = collect($data->get('quotes'))
            ->mapWithKeys(fn($value, $key) => [str_replace($baseCurrency, '', $key) => floatval($value)])
            ->all();

        return new Rates(
            $baseCurrency,
            // @phpstan-ignore-next-line
            $formattedQuotes,
            CarbonImmutable::createFromTimestamp(intval($data->get('timestamp')))
        );
    }

    /**
     * @param array<int, string> $currencies
     *
     * @return Collection<string, mixed>
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    private function makeRequest(string $baseCurrency, array $currencies): Collection
    {
        return $this->client()
            ->get('/live', [
                'access_key' => $this->accessKey,
                'source' => $baseCurrency,
                'format' => 1,
                'currencies' => implode(',', $currencies),
            ])
            ->throw()
            ->collect();
    }

    private function client(): PendingRequest
    {
        return $this->client
            ->baseUrl($this->baseUrl)
            ->asJson()
            ->acceptJson();
    }
}
