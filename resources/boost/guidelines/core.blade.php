## Laravel CEP

### Overview
Laravel CEP is a package for querying Brazilian postal codes (CEP). It looks up addresses using multiple API providers with automatic fallback and caches results in a local database table for faster subsequent queries.

**Namespace:** `JeffersonGoncalves\Cep`
**Service Provider:** `CepServiceProvider` (auto-discovered)

### Key Concepts
- **Database-first lookup:** The `Cep` model checks the local `cep` table before hitting external APIs.
- **Multi-provider fallback:** `CepService` tries BrasilAPI, ViaCEP, and AwesomeAPI in sequence. If one fails, it falls to the next.
- **Auto-caching:** Every successful API response is persisted via `Cep::updateByCep()` using `updateOrCreate`.
- **Input sanitization:** CEP strings are stripped of punctuation, left-padded to 8 digits, and validated before lookup.

### Architecture

The package has three main classes:

- `Cep` (Model) -- Entry point. Use `Cep::findByCep($cep)` or `Cep::checkCep($cep)`.
- `CepService` -- Abstract service that queries external APIs with fallback chain.
- `CepSupport` -- Returns the empty result array structure.

### Usage

@verbatim
<code-snippet name="find-by-cep" lang="php">
use JeffersonGoncalves\Cep\Models\Cep;

// Find address by CEP (returns array with cep, state, city, neighborhood, street)
$address = Cep::findByCep('01001000');

// Check if a CEP is valid and returns data
$isValid = Cep::checkCep('01001-000');
</code-snippet>
@endverbatim

### Result Array Structure

@verbatim
<code-snippet name="result-structure" lang="php">
// All lookup methods return this structure:
[
    'cep' => '01001000',
    'state' => 'SP',
    'city' => 'Sao Paulo',
    'neighborhood' => 'Se',
    'street' => 'Praca da Se',
]

// Empty result when CEP is not found:
[
    'cep' => '',
    'state' => '',
    'city' => '',
    'neighborhood' => '',
    'street' => '',
]
</code-snippet>
@endverbatim

### Database

The package publishes a migration `create_cep_table` with columns:
- `cep` (string, 8 chars, primary key)
- `state` (enum of Brazilian state abbreviations, nullable)
- `city`, `neighborhood`, `street` (string, nullable)
- `timestamps`

### API Providers (Fallback Order)
1. **BrasilAPI** -- `https://brasilapi.com.br/api/cep/v1/{cep}`
2. **ViaCEP** -- `https://viacep.com.br/ws/{cep}/json/`
3. **AwesomeAPI** -- `https://cep.awesomeapi.com.br/json/{cep}`

### Conventions
- The `Cep` model uses `cep` as primary key (string, non-incrementing).
- The table name is `cep` (not `ceps`).
- All external API calls use a 5-second timeout.
- SSL verification is skipped only when no CA certificate is configured.
- The package has no config file; behavior is controlled through the model and service classes.
