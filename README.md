<div class="filament-hidden">

![Laravel Cep](https://raw.githubusercontent.com/jeffersongoncalves/laravel-cep/master/art/jeffersongoncalves-laravel-cep.png)

</div>

# Laravel Cep

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-cep.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-cep)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-cep/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-cep/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-cep.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-cep)

A simple and efficient PHP package for querying Brazilian postal codes (CEP). This package provides an easy way to retrieve address information from Brazilian ZIP codes through multiple providers.

## Installation

You can install the package via composer:

```bash
composer require jeffersongoncalves/laravel-cep
```

## Usage

Publish migration file.

```bash
php artisan vendor:publish --tag=cep-migrations
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jèfferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
