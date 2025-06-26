<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\Cep\Models\Cep;

describe('Cep Model', function () {
    beforeEach(function () {
        // Clear any existing CEP records
        Cep::query()->delete();
    });

    describe('checkCep method', function () {
        it('returns true for valid CEP that exists in database', function () {
            // Create a CEP record in database
            Cep::create([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ]);

            expect(Cep::checkCep('01310-100'))->toBeTrue();
        });

        it('returns false for empty CEP', function () {
            expect(Cep::checkCep(''))->toBeFalse();
            expect(Cep::checkCep(null))->toBeFalse();
        });

        it('returns false for invalid CEP format', function () {
            expect(Cep::checkCep('123'))->toBeFalse();
            expect(Cep::checkCep('invalid'))->toBeFalse();
        });

        it('returns true when CEP is found via API', function () {
            Http::fake([
                'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                    'cep' => '01310100',
                    'state' => 'SP',
                    'city' => 'São Paulo',
                    'neighborhood' => 'Bela Vista',
                    'street' => 'Avenida Paulista',
                ]),
            ]);

            expect(Cep::checkCep('01310-100'))->toBeTrue();
        });
    });

    describe('findByCep method', function () {
        it('returns empty result for null or empty CEP', function () {
            $expected = [
                'cep' => '',
                'state' => '',
                'city' => '',
                'neighborhood' => '',
                'street' => '',
            ];

            expect(Cep::findByCep(null))->toBe($expected);
            expect(Cep::findByCep(''))->toBe($expected);
        });

        it('returns empty result for CEP shorter than 8 digits', function () {
            $expected = [
                'cep' => '',
                'state' => '',
                'city' => '',
                'neighborhood' => '',
                'street' => '',
            ];

            expect(Cep::findByCep('123'))->toBe($expected);
            expect(Cep::findByCep('12345'))->toBe($expected);
        });

        it('normalizes CEP format correctly', function () {
            // Create a CEP record
            Cep::create([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ]);

            // Test various formats
            $result1 = Cep::findByCep('01310-100');
            $result2 = Cep::findByCep('01.310.100');
            $result3 = Cep::findByCep('01310100');
            $result4 = Cep::findByCep('1310100'); // Should pad with zero

            expect($result1['cep'])->toBe('01310100');
            expect($result2['cep'])->toBe('01310100');
            expect($result3['cep'])->toBe('01310100');
            expect($result4['cep'])->toBe('01310100');
        });

        it('returns data from database when CEP exists', function () {
            $cepData = [
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ];

            Cep::create($cepData);

            $result = Cep::findByCep('01310-100');

            expect($result['cep'])->toBe('01310100');
            expect($result['state'])->toBe('SP');
            expect($result['city'])->toBe('São Paulo');
            expect($result['neighborhood'])->toBe('Bela Vista');
            expect($result['street'])->toBe('Avenida Paulista');
        });

        it('fetches from BrasilAPI when not in database', function () {
            Http::fake([
                'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                    'cep' => '01310100',
                    'state' => 'SP',
                    'city' => 'São Paulo',
                    'neighborhood' => 'Bela Vista',
                    'street' => 'Avenida Paulista',
                ]),
            ]);

            $result = Cep::findByCep('01310-100');

            expect($result['cep'])->toBe('01310100');
            expect($result['state'])->toBe('SP');
            expect($result['city'])->toBe('São Paulo');
            expect($result['neighborhood'])->toBe('Bela Vista');
            expect($result['street'])->toBe('Avenida Paulista');

            // Verify it was saved to database
            $dbRecord = Cep::find('01310100');
            expect($dbRecord)->not->toBeNull();
            expect($dbRecord->state)->toBe('SP');
        });

        it('falls back to ViaCEP when BrasilAPI fails', function () {
            Http::fake([
                'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([], 500),
                'https://viacep.com.br/ws/01310100/json/' => Http::response([
                    'cep' => '01310-100',
                    'uf' => 'SP',
                    'localidade' => 'São Paulo',
                    'bairro' => 'Bela Vista',
                    'logradouro' => 'Avenida Paulista',
                ]),
            ]);

            $result = Cep::findByCep('01310-100');

            expect($result['cep'])->toBe('01310100');
            expect($result['state'])->toBe('SP');
            expect($result['city'])->toBe('São Paulo');
            expect($result['neighborhood'])->toBe('Bela Vista');
            expect($result['street'])->toBe('Avenida Paulista');
        });

        it('falls back to AwesomeAPI when both BrasilAPI and ViaCEP fail', function () {
            Http::fake([
                'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([], 500),
                'https://viacep.com.br/ws/01310100/json/' => Http::response([], 500),
                'https://cep.awesomeapi.com.br/json/01310100' => Http::response([
                    'code' => null,
                    'state' => 'SP',
                    'city' => 'São Paulo',
                    'district' => 'Bela Vista',
                    'address' => 'Avenida Paulista',
                ]),
            ]);

            $result = Cep::findByCep('01310-100');

            expect($result['cep'])->toBe('01310100');
            expect($result['state'])->toBe('SP');
            expect($result['city'])->toBe('São Paulo');
            expect($result['neighborhood'])->toBe('Bela Vista');
            expect($result['street'])->toBe('Avenida Paulista');
        });

        it('returns empty result when all APIs fail', function () {
            Http::fake([
                'https://brasilapi.com.br/api/cep/v1/01310100' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
                },
                'https://viacep.com.br/ws/01310100/json/' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
                },
                'https://cep.awesomeapi.com.br/json/01310100' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
                },
            ]);

            $expected = [
                'cep' => '',
                'state' => '',
                'city' => '',
                'neighborhood' => '',
                'street' => '',
            ];

            $result = Cep::findByCep('01310-100');
            expect($result)->toBe($expected);
        });

        it('handles AwesomeAPI error response correctly', function () {
            Http::fake([
                'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([], 500),
                'https://viacep.com.br/ws/01310100/json/' => Http::response([], 500),
                'https://cep.awesomeapi.com.br/json/01310100' => Http::response([
                    'code' => 'not_found',
                    'message' => 'CEP not found',
                ]),
            ]);

            $expected = [
                'cep' => '',
                'state' => '',
                'city' => '',
                'neighborhood' => '',
                'street' => '',
            ];

            $result = Cep::findByCep('01310-100');
            expect($result)->toBe($expected);
        });

        it('handles null response from AwesomeAPI', function () {
            Http::fake([
                'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([], 500),
                'https://viacep.com.br/ws/01310100/json/' => Http::response([], 500),
                'https://cep.awesomeapi.com.br/json/01310100' => Http::response(null),
            ]);

            $expected = [
                'cep' => '',
                'state' => '',
                'city' => '',
                'neighborhood' => '',
                'street' => '',
            ];

            $result = Cep::findByCep('01310-100');
            expect($result)->toBe($expected);
        });

        it('handles missing optional fields from APIs', function () {
            Http::fake([
                'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                    'cep' => '01310100',
                    'state' => 'SP',
                    'city' => 'São Paulo',
                    // neighborhood and street are missing
                ]),
            ]);

            $result = Cep::findByCep('01310-100');

            expect($result['cep'])->toBe('01310100');
            expect($result['state'])->toBe('SP');
            expect($result['city'])->toBe('São Paulo');
            expect($result['neighborhood'])->toBe('');
            expect($result['street'])->toBe('');
        });
    });

    describe('CEP formatting and validation', function () {
        it('handles various CEP formats correctly', function () {
            $testCases = [
                '01310-100' => '01310100',
                '01.310.100' => '01310100',
                '01310100' => '01310100',
                '1310100' => '01310100', // Should pad with zero
                '01310/100' => '01310100',
                '01310(100)' => '01310100',
                '01310 100' => '01310100',
            ];

            foreach ($testCases as $input => $expected) {
                // Create a record for each test
                Cep::query()->delete();
                Cep::create([
                    'cep' => $expected,
                    'state' => 'SP',
                    'city' => 'Test City',
                    'neighborhood' => 'Test Neighborhood',
                    'street' => 'Test Street',
                ]);

                $result = Cep::findByCep($input);
                expect($result['cep'])->toBe($expected, "Failed for input: {$input}");
            }
        });
    });
});
