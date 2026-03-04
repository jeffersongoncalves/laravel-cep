---
name: cep-development
description: Development guide for laravel-cep, a Brazilian postal code (CEP) lookup package with multi-provider fallback and local database caching.
---

# CEP Development Skill

## When to use this skill

- When developing or extending the laravel-cep package
- When adding new CEP API providers to the fallback chain
- When modifying the Cep model or CepService behavior
- When writing tests for CEP lookup functionality
- When debugging CEP lookup failures or caching issues

## Setup

### Requirements
- PHP 8.2+
- Laravel 11, 12, or 13
- `spatie/laravel-package-tools` ^1.14

### Installation

```bash
composer require jeffersongoncalves/laravel-cep
```

### Run Migration

```bash
php artisan migrate
```

This creates the `cep` table with columns: `cep` (PK, string 8), `state` (enum), `city`, `neighborhood`, `street`, and timestamps.

## Package Structure

```
src/
  CepServiceProvider.php        # Registers migration via spatie/laravel-package-tools
  Models/
    Cep.php                     # Eloquent model, main entry point
  Services/
    CepService.php              # External API lookup with fallback chain
  Support/
    CepSupport.php              # Empty result array helper
database/
  migrations/
    create_cep_table.php        # Migration for the cep table
```

## Features

### CEP Lookup (Model Entry Point)

The `Cep` model is the primary entry point. It first checks the local database, then falls back to external APIs:

```php
use JeffersonGoncalves\Cep\Models\Cep;

// Find address by CEP - returns associative array
$address = Cep::findByCep('01001000');
// Returns: ['cep' => '01001000', 'state' => 'SP', 'city' => '...', 'neighborhood' => '...', 'street' => '...']

// Accepts various formats - automatically sanitized
$address = Cep::findByCep('01001-000');
$address = Cep::findByCep('01.001-000');

// Check if CEP exists and is valid
$isValid = Cep::checkCep('01001000'); // true or false
```

### Input Sanitization

The `findByCep` method automatically:
1. Returns empty result for null/empty input
2. Strips characters: `.`, `-`, `/`, `(`, `)`, spaces
3. Left-pads with zeros to 8 digits
4. Truncates to 8 characters
5. Validates minimum length of 8

```php
// All these are equivalent:
Cep::findByCep('01001000');
Cep::findByCep('01001-000');
Cep::findByCep('01.001-000');
Cep::findByCep('1001000');  // Padded to 01001000
```

### Multi-Provider Fallback (CepService)

The `CepService::findByCep()` method queries three APIs in sequence. Each failed attempt (ConnectionException) is silently caught and the next provider is tried:

1. **BrasilAPI**: `https://brasilapi.com.br/api/cep/v1/{cep}`
2. **ViaCEP**: `https://viacep.com.br/ws/{cep}/json/`
3. **AwesomeAPI**: `https://cep.awesomeapi.com.br/json/{cep}`

```php
use JeffersonGoncalves\Cep\Services\CepService;

// Direct API lookup (bypasses database cache)
$result = CepService::findByCep('01001000');
```

### Auto-Caching via Database

Every successful API response is automatically persisted to the `cep` table using `Cep::updateByCep()`:

```php
// This is called internally after each successful API response
Cep::updateByCep($cep, $state, $city, $neighborhood, $street);

// Uses updateOrCreate - inserts new or updates existing
Cep::updateOrCreate(
    ['cep' => $cep],
    ['state' => $state, 'city' => $city, 'neighborhood' => $neighborhood, 'street' => $street]
);
```

### Empty Result Structure

When no CEP is found, `CepSupport::getResult()` returns the standard empty array:

```php
use JeffersonGoncalves\Cep\Support\CepSupport;

$empty = CepSupport::getResult();
// ['cep' => '', 'state' => '', 'city' => '', 'neighborhood' => '', 'street' => '']
```

### Cep Model Details

```php
// Model configuration
$table = 'cep';           // Not 'ceps'
$primaryKey = 'cep';      // String primary key
$incrementing = false;    // Non-incrementing
$guarded = [];            // Mass-assignable
```

## Configuration

This package has no configuration file. Behavior is controlled through:

- **API providers**: Hardcoded in `CepService::findByCep()` with 5-second timeouts
- **SSL verification**: Automatically skipped when `curl.cainfo` and `openssl.cafile` are not configured
- **Database table**: Fixed as `cep` via the model

## Testing Patterns

### Testing CEP Lookup

```php
use JeffersonGoncalves\Cep\Models\Cep;

it('finds a valid CEP', function () {
    // Seed the database to avoid external API calls
    Cep::create([
        'cep' => '01001000',
        'state' => 'SP',
        'city' => 'Sao Paulo',
        'neighborhood' => 'Se',
        'street' => 'Praca da Se',
    ]);

    $result = Cep::findByCep('01001000');

    expect($result)
        ->toBeArray()
        ->and($result['cep'])->toBe('01001000')
        ->and($result['state'])->toBe('SP');
});
```

### Testing CEP Validation

```php
it('returns empty for invalid CEP', function () {
    $result = Cep::findByCep(null);

    expect($result['cep'])->toBe('');
});

it('sanitizes CEP input', function () {
    Cep::create([
        'cep' => '01001000',
        'state' => 'SP',
        'city' => 'Sao Paulo',
        'neighborhood' => 'Se',
        'street' => 'Praca da Se',
    ]);

    $result = Cep::findByCep('01.001-000');

    expect($result['cep'])->toBe('01001000');
});
```

### Testing checkCep

```php
it('checks if CEP exists', function () {
    Cep::create([
        'cep' => '01001000',
        'state' => 'SP',
        'city' => 'Sao Paulo',
        'neighborhood' => 'Se',
        'street' => 'Praca da Se',
    ]);

    expect(Cep::checkCep('01001000'))->toBeTrue();
    expect(Cep::checkCep(null))->toBeFalse();
});
```

### Mocking External APIs

```php
use Illuminate\Support\Facades\Http;

it('falls back to ViaCEP when BrasilAPI fails', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(null, 500),
        'viacep.com.br/*' => Http::response([
            'cep' => '01001-000',
            'uf' => 'SP',
            'localidade' => 'Sao Paulo',
            'bairro' => 'Se',
            'logradouro' => 'Praca da Se',
        ]),
    ]);

    $result = \JeffersonGoncalves\Cep\Services\CepService::findByCep('01001000');

    expect($result['state'])->toBe('SP');
});
```

### Running Tests

```bash
# Run all tests
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage

# Static analysis
vendor/bin/phpstan analyse

# Code formatting
vendor/bin/pint
```

## Adding a New Provider

To add a new CEP API provider, add a new `try/catch` block in `CepService::findByCep()` following the existing pattern:

```php
try {
    $request = Http::timeout(5)->withOptions($options)
        ->get("https://newprovider.com/api/{$cep}")->json();
    if (! empty($request['cep'])) {
        Cep::updateByCep(
            $cep,
            $request['state_field'],
            $request['city_field'],
            $request['neighborhood_field'] ?? '',
            $request['street_field'] ?? ''
        );
        return [
            'cep' => $cep,
            'state' => $request['state_field'],
            'city' => $request['city_field'],
            'neighborhood' => $request['neighborhood_field'] ?? '',
            'street' => $request['street_field'] ?? '',
        ];
    }
} catch (ConnectionException $ignored) {
}
```

Key points:
- Map provider-specific field names to the standard keys (state, city, neighborhood, street)
- Always use `?? ''` for optional fields
- Call `Cep::updateByCep()` to cache the result
- Catch `ConnectionException` to allow fallback to next provider
