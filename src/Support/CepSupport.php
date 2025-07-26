<?php

namespace JeffersonGoncalves\Cep\Support;

abstract class CepSupport
{
    public static function getResult(): array
    {
        return [
            'cep' => '',
            'state' => '',
            'city' => '',
            'neighborhood' => '',
            'street' => '',
        ];
    }
}
