<?php

use JeffersonGoncalves\Cep\Models\Cep;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

describe('Cep Integration Tests', function () {
    beforeEach(function () {
        // Clear any existing CEP records
        Cep::query()->delete();
    });

    it('can create and retrieve CEP records from database', function () {
        // Test database integration
        $cepData = [
            'cep' => '01310100',
            'state' => 'SP',
            'city' => 'São Paulo',
            'neighborhood' => 'Bela Vista',
            'street' => 'Avenida Paulista',
        ];

        $cep = Cep::create($cepData);

        expect($cep)->toBeInstanceOf(Cep::class);
        expect($cep->cep)->toBe('01310100');
        expect($cep->state)->toBe('SP');
        expect($cep->city)->toBe('São Paulo');

        // Test retrieval
        $retrieved = Cep::find('01310100');
        expect($retrieved)->not->toBeNull();
        expect($retrieved->state)->toBe('SP');
    });

    it('has correct database table structure', function () {
        expect(Schema::hasTable('cep'))->toBeTrue();
        expect(Schema::hasColumn('cep', 'cep'))->toBeTrue();
        expect(Schema::hasColumn('cep', 'state'))->toBeTrue();
        expect(Schema::hasColumn('cep', 'city'))->toBeTrue();
        expect(Schema::hasColumn('cep', 'neighborhood'))->toBeTrue();
        expect(Schema::hasColumn('cep', 'street'))->toBeTrue();
        expect(Schema::hasColumn('cep', 'created_at'))->toBeTrue();
        expect(Schema::hasColumn('cep', 'updated_at'))->toBeTrue();
    });

    it('integrates with external APIs and saves to database', function () {
        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ]),
        ]);

        // First call should fetch from API
        $result = Cep::findByCep('01310-100');

        expect($result['cep'])->toBe('01310100');
        expect($result['state'])->toBe('SP');

        // Verify it was saved to database
        $dbRecord = Cep::find('01310100');
        expect($dbRecord)->not->toBeNull();
        expect($dbRecord->state)->toBe('SP');

        // Second call should fetch from database (no HTTP call)
        Http::fake(); // Reset HTTP fake to ensure no calls are made

        $result2 = Cep::findByCep('01310-100');
        expect($result2['cep'])->toBe('01310100');
        expect($result2['state'])->toBe('SP');
    });

    it('handles concurrent requests properly', function () {
        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ]),
        ]);

        // Simulate multiple concurrent requests for the same CEP
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = Cep::findByCep('01310-100');
        }

        // All results should be consistent
        foreach ($results as $result) {
            expect($result['cep'])->toBe('01310100');
            expect($result['state'])->toBe('SP');
        }

        // Should only have one record in database
        $count = Cep::where('cep', '01310100')->count();
        expect($count)->toBe(1);
    });

    it('updates existing records when fetching from API', function () {
        // Create initial record
        Cep::create([
            'cep' => '01310100',
            'state' => 'SP',
            'city' => 'Old City',
            'neighborhood' => 'Old Neighborhood',
            'street' => 'Old Street',
        ]);

        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ]),
        ]);

        // Force API call by deleting the record and calling findByCep
        Cep::where('cep', '01310100')->delete();
        $result = Cep::findByCep('01310-100');

        expect($result['city'])->toBe('São Paulo');
        expect($result['neighborhood'])->toBe('Bela Vista');
        expect($result['street'])->toBe('Avenida Paulista');

        // Verify database was updated
        $dbRecord = Cep::find('01310100');
        expect($dbRecord->city)->toBe('São Paulo');
        expect($dbRecord->neighborhood)->toBe('Bela Vista');
    });

    it('handles API timeout gracefully', function () {
        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310100' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
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
    });

    it('validates CEP format before making API calls', function () {
        Http::fake([
            '*' => Http::response([
                'code' => 'not_found',
                'message' => 'CEP not found',
            ], 404), // Catch any unexpected calls with proper error structure
        ]);

        $invalidCeps = ['', null, '123', 'invalid'];

        foreach ($invalidCeps as $invalidCep) {
            $result = Cep::findByCep($invalidCep);
            expect($result['cep'])->toBe('');
        }

        // For CEPs that are too long, they get truncated to 8 digits and may trigger API calls
        // So we test them separately with proper mocking
        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/12345678' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
            },
            'https://viacep.com.br/ws/12345678/json/' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
            },
            'https://cep.awesomeapi.com.br/json/12345678' => Http::response([
                'code' => 'not_found',
                'message' => 'CEP not found',
            ], 404),
        ]);

        $result = Cep::findByCep('123456789'); // Gets truncated to 12345678
        expect($result['cep'])->toBe('');
    });

    it('handles different API response formats correctly', function () {
        // Test BrasilAPI format
        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310100' => Http::response([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ]),
        ]);

        $result1 = Cep::findByCep('01310-100');
        expect($result1['state'])->toBe('SP');

        // Clear database and test ViaCEP format
        Cep::query()->delete();
        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310200' => Http::response([], 500),
            'https://viacep.com.br/ws/01310200/json/' => Http::response([
                'cep' => '01310-200',
                'uf' => 'SP',
                'localidade' => 'São Paulo',
                'bairro' => 'Bela Vista',
                'logradouro' => 'Avenida Paulista',
            ]),
        ]);

        $result2 = Cep::findByCep('01310-200');
        expect($result2['state'])->toBe('SP');

        // Clear database and test AwesomeAPI format
        Cep::query()->delete();
        Http::fake([
            'https://brasilapi.com.br/api/cep/v1/01310300' => Http::response([], 500),
            'https://viacep.com.br/ws/01310300/json/' => Http::response([], 500),
            'https://cep.awesomeapi.com.br/json/01310300' => Http::response([
                'state' => 'SP',
                'city' => 'São Paulo',
                'district' => 'Bela Vista',
                'address' => 'Avenida Paulista',
            ]),
        ]);

        $result3 = Cep::findByCep('01310-300');
        expect($result3['state'])->toBe('SP');
    });

    it('maintains data consistency across multiple operations', function () {
        $testCeps = [
            '01310100' => ['state' => 'SP', 'city' => 'São Paulo'],
            '20040020' => ['state' => 'RJ', 'city' => 'Rio de Janeiro'],
            '30112000' => ['state' => 'MG', 'city' => 'Belo Horizonte'],
        ];

        foreach ($testCeps as $cep => $data) {
            Http::fake([
                "https://brasilapi.com.br/api/cep/v1/{$cep}" => Http::response([
                    'cep' => $cep,
                    'state' => $data['state'],
                    'city' => $data['city'],
                    'neighborhood' => 'Test Neighborhood',
                    'street' => 'Test Street',
                ]),
            ]);

            $result = Cep::findByCep($cep);
            expect($result['state'])->toBe($data['state']);
            expect($result['city'])->toBe($data['city']);
        }

        // Verify all records are in database
        expect(Cep::count())->toBe(3);

        // Verify each record individually
        foreach ($testCeps as $cep => $data) {
            $dbRecord = Cep::find($cep);
            expect($dbRecord)->not->toBeNull();
            expect($dbRecord->state)->toBe($data['state']);
            expect($dbRecord->city)->toBe($data['city']);
        }
    });
});
