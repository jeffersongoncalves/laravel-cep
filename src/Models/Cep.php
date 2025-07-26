<?php

namespace JeffersonGoncalves\Cep\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use JeffersonGoncalves\Cep\Services\CepService;
use JeffersonGoncalves\Cep\Support\CepSupport;

class Cep extends Model
{
    public $incrementing = false;

    protected $table = 'cep';

    protected $primaryKey = 'cep';

    protected $guarded = [];

    public static function checkCep(?string $cep): bool
    {
        $result = self::findByCep($cep);

        return ! empty($result['cep']);
    }

    public static function findByCep(?string $cep): array
    {
        if (empty($cep)) {
            return CepSupport::getResult();
        }
        $cep = mb_substr(str_pad(str_replace(['.', '-', '/', '(', ')', ' '], '', $cep), 8, '0', STR_PAD_LEFT), 0, 8);

        if (mb_strlen($cep) < 8) {
            return CepSupport::getResult();
        }

        try {
            return self::query()->findOrFail($cep)->toArray();
        } catch (ModelNotFoundException $ignored) {
            return CepService::findByCep($cep);
        }
    }

    public static function updateByCep(string $cep, string $state, string $city, string $neighborhood, string $street): void
    {
        Cep::updateOrCreate([
            'cep' => $cep,
        ], [
            'state' => $state,
            'city' => $city,
            'neighborhood' => $neighborhood,
            'street' => $street,
        ]);
    }
}
