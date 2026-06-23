<?php

namespace JeffersonGoncalves\Cep\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\Cep\Models\Cep;
use JeffersonGoncalves\Cep\Support\CepSupport;

abstract class CepService
{
    public static function findByCep(?string $cep): array
    {
        foreach (self::providers() as $provider) {
            try {
                $request = Http::timeout(self::timeout())
                    ->withOptions(self::httpOptions())
                    ->get(($provider['url'])($cep))
                    ->json();

                if (! empty($request['cep'])) {
                    $address = ($provider['map'])($request);

                    Cep::updateByCep(
                        $cep,
                        $address['state'],
                        $address['city'],
                        $address['neighborhood'],
                        $address['street']
                    );

                    return ['cep' => $cep] + $address;
                }
            } catch (ConnectionException $ignored) {
            }
        }

        return CepSupport::getResult();
    }

    /**
     * The list of CEP providers, each describing how to build the request URL
     * and how to map the provider response to a normalized address array.
     *
     * @return array<int, array{url: callable, map: callable}>
     */
    protected static function providers(): array
    {
        return [
            [
                'url' => fn (string $cep): string => "https://brasilapi.com.br/api/cep/v1/{$cep}",
                'map' => fn (array $response): array => [
                    'state' => $response['state'],
                    'city' => $response['city'],
                    'neighborhood' => $response['neighborhood'] ?? '',
                    'street' => $response['street'] ?? '',
                ],
            ],
            [
                'url' => fn (string $cep): string => "https://viacep.com.br/ws/{$cep}/json/",
                'map' => fn (array $response): array => [
                    'state' => $response['uf'],
                    'city' => $response['localidade'],
                    'neighborhood' => $response['bairro'] ?? '',
                    'street' => $response['logradouro'] ?? '',
                ],
            ],
            [
                'url' => fn (string $cep): string => "https://cep.awesomeapi.com.br/json/{$cep}",
                'map' => fn (array $response): array => [
                    'state' => $response['state'],
                    'city' => $response['city'],
                    'neighborhood' => $response['district'] ?? '',
                    'street' => $response['address'] ?? '',
                ],
            ],
        ];
    }

    protected static function timeout(): int
    {
        return (int) config('cep.timeout', 5);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function httpOptions(): array
    {
        return [
            'verify' => (bool) config('cep.verify_ssl', true),
        ];
    }
}
