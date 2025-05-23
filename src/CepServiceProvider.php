<?php

namespace JeffersonGoncalves\Cep;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CepServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-cep')
            ->hasMigration('create_cep_table');
    }
}
