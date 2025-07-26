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
        $cacert = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        $options = [];
        if (! $cacert || ! file_exists($cacert)) {
            $options['verify'] = false;
        }
        try {
            $request = Http::timeout(5)->withOptions($options)->get("https://brasilapi.com.br/api/cep/v1/{$cep}")->json();
            if (! empty($request['cep'])) {
                Cep::updateByCep($cep, $request['state'], $request['city'], $request['neighborhood'] ?? '', $request['street'] ?? '');

                return [
                    'cep' => $cep,
                    'state' => $request['state'],
                    'city' => $request['city'],
                    'neighborhood' => $request['neighborhood'] ?? '',
                    'street' => $request['street'] ?? '',
                ];
            }
        } catch (ConnectionException $ignored) {
        }
        try {
            $request = Http::timeout(5)->withOptions($options)->get("https://viacep.com.br/ws/{$cep}/json/")->json();
            if (! empty($request['cep'])) {
                Cep::updateByCep($cep, $request['uf'], $request['localidade'], $request['bairro'] ?? '', $request['logradouro'] ?? '');

                return [
                    'cep' => $cep,
                    'state' => $request['uf'],
                    'city' => $request['localidade'],
                    'neighborhood' => $request['bairro'] ?? '',
                    'street' => $request['logradouro'] ?? '',
                ];
            }
        } catch (ConnectionException $ignored) {
        }
        try {
            $request = Http::timeout(5)->withOptions($options)->get("https://cep.awesomeapi.com.br/json/{$cep}")->json();
            if (! empty($request['cep'])) {
                Cep::updateByCep($cep, $request['state'], $request['city'], $request['district'] ?? '', $request['address'] ?? '');

                return [
                    'cep' => $cep,
                    'state' => $request['state'],
                    'city' => $request['city'],
                    'neighborhood' => $request['district'] ?? '',
                    'street' => $request['address'] ?? '',
                ];
            }
        } catch (ConnectionException $ignored) {
        }

        return CepSupport::getResult();
    }
}
