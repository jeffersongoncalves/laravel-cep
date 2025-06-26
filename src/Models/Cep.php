<?php

namespace JeffersonGoncalves\Cep\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\Cep\Jobs\FlushCache;

class Cep extends Model
{
    use Cachable;

    public $incrementing = false;

    protected int $cacheCooldownSeconds = 86400;

    protected $table = 'cep';

    protected $primaryKey = 'cep';

    protected $guarded = [];

    public static function booted(): void
    {
        static::created(fn(Model $model) => FlushCache::dispatch());
        static::updated(fn(Model $model) => FlushCache::dispatch());
        static::deleted(fn(Model $model) => FlushCache::dispatch());
    }

    public static function checkCep(?string $cep): bool
    {
        $result = self::findByCep($cep);

        return !empty($result['cep']);
    }

    public static function findByCep(?string $cep): array
    {
        if (empty($cep)) {
            return self::getResult();
        }
        $cep = mb_substr(str_pad(str_replace(['.', '-', '/', '(', ')', ' '], '', $cep), 8, '0', STR_PAD_LEFT), 0, 8);

        if (mb_strlen($cep) < 8) {
            return self::getResult();
        }

        try {
            return self::query()->findOrFail($cep)->toArray();
        } catch (ModelNotFoundException $ignored) {
            try {
                $request = Http::timeout(5)->get("https://brasilapi.com.br/api/cep/v1/{$cep}")->json();
                if (!empty($request['cep'])) {
                    $data = [
                        'cep' => $cep,
                        'state' => $request['state'],
                        'city' => $request['city'],
                        'neighborhood' => $request['neighborhood'] ?? '',
                        'street' => $request['street'] ?? '',
                    ];
                    self::updateOrCreate([
                        'cep' => $cep,
                    ], [
                        'state' => $request['state'],
                        'city' => $request['city'],
                        'neighborhood' => $request['neighborhood'] ?? '',
                        'street' => $request['street'] ?? '',
                    ]);

                    return $data;
                }
            } catch (ConnectionException $ignored) {
            }
            try {
                $request = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/")->json();
                if (!empty($request['cep'])) {
                    $data = [
                        'cep' => $cep,
                        'state' => $request['uf'],
                        'city' => $request['localidade'],
                        'neighborhood' => $request['bairro'] ?? '',
                        'street' => $request['logradouro'] ?? '',
                    ];
                    self::updateOrCreate([
                        'cep' => $cep,
                    ], [
                        'state' => $request['uf'],
                        'city' => $request['localidade'],
                        'neighborhood' => $request['bairro'] ?? '',
                        'street' => $request['logradouro'] ?? '',
                    ]);

                    return $data;
                }
            } catch (ConnectionException $ignored) {
            }
            try {
                $request = Http::timeout(5)->get("https://cep.awesomeapi.com.br/json/{$cep}")->json();
                if (is_null($request)) {
                    return self::getResult();
                }
                if (!empty($request['code'])) {
                    return self::getResult();
                }
                $data = [
                    'cep' => $cep,
                    'state' => $request['state'],
                    'city' => $request['city'],
                    'neighborhood' => $request['district'] ?? '',
                    'street' => $request['address'] ?? '',
                ];
                self::updateOrCreate([
                    'cep' => $cep,
                ], [
                    'state' => $request['state'],
                    'city' => $request['city'],
                    'neighborhood' => $request['district'] ?? '',
                    'street' => $request['address'] ?? '',
                ]);

                return $data;
            } catch (ConnectionException $ignored) {
            }
            return self::getResult();
        }
    }

    private static function getResult(): array
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
