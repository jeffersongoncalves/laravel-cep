<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\Cep\Models\Cep;

describe('CEP validation', function () {
    beforeEach(function () {
        Cep::query()->delete();
    });

    it('rejects non-numeric input without performing any HTTP request', function () {
        Http::fake();

        foreach (['invalid', 'abcdefgh', '0invalid', '12.ab-cd'] as $input) {
            $result = Cep::findByCep($input);
            expect($result['cep'])->toBe('');
        }

        Http::assertNothingSent();
    });

    it('does not perform HTTP requests for checkCep with non-numeric input', function () {
        Http::fake();

        expect(Cep::checkCep('invalid'))->toBeFalse();

        Http::assertNothingSent();
    });
});

describe('CEP database-only lookup', function () {
    beforeEach(function () {
        Cep::query()->delete();
    });

    it('returns the stored record without calling providers', function () {
        Http::fake();

        Cep::create([
            'cep' => '01310100',
            'state' => 'SP',
            'city' => 'São Paulo',
            'neighborhood' => 'Bela Vista',
            'street' => 'Avenida Paulista',
        ]);

        $result = Cep::findByCepInDatabase('01310-100');

        expect($result['state'])->toBe('SP');
        Http::assertNothingSent();
    });

    it('returns an empty result when the record is missing without calling providers', function () {
        Http::fake();

        $result = Cep::findByCepInDatabase('99999999');

        expect($result['cep'])->toBe('');
        Http::assertNothingSent();
    });
});

describe('CEP cache TTL', function () {
    beforeEach(function () {
        Cep::query()->delete();
    });

    it('serves a fresh record from the database without HTTP when within TTL', function () {
        config()->set('cep.cache_ttl', 60);
        Http::fake();

        Cep::create([
            'cep' => '01310100',
            'state' => 'SP',
            'city' => 'São Paulo',
            'neighborhood' => 'Bela Vista',
            'street' => 'Avenida Paulista',
        ]);

        $result = Cep::findByCep('01310100');

        expect($result['state'])->toBe('SP');
        Http::assertNothingSent();
    });

    it('re-fetches from providers when the stored record is stale', function () {
        config()->set('cep.cache_ttl', 60);

        Cep::create([
            'cep' => '01310100',
            'state' => 'RJ',
            'city' => 'Old City',
            'neighborhood' => 'Old',
            'street' => 'Old',
        ]);

        // Age the record beyond the TTL without touching timestamps logic.
        Cep::query()->where('cep', '01310100')->update(['updated_at' => now()->subSeconds(120)]);

        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ]),
        ]);

        $result = Cep::findByCep('01310100');

        expect($result['state'])->toBe('SP');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'brasilapi.com.br'));
    });

    it('caches forever when the TTL is null', function () {
        config()->set('cep.cache_ttl', null);
        Http::fake();

        Cep::create([
            'cep' => '01310100',
            'state' => 'SP',
            'city' => 'São Paulo',
            'neighborhood' => 'Bela Vista',
            'street' => 'Avenida Paulista',
        ]);

        Cep::query()->where('cep', '01310100')->update(['updated_at' => now()->subYears(5)]);

        $result = Cep::findByCep('01310100');

        expect($result['state'])->toBe('SP');
        Http::assertNothingSent();
    });
});

describe('CEP HTTP configuration', function () {
    beforeEach(function () {
        Cep::query()->delete();
    });

    it('respects custom timeout and ssl verification configuration', function () {
        config()->set('cep.timeout', 2);
        config()->set('cep.verify_ssl', false);

        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ]),
        ]);

        $result = Cep::findByCep('01310100');

        expect($result['state'])->toBe('SP');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'brasilapi.com.br'));
    });
});
