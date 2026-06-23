<?php

namespace JeffersonGoncalves\Cep\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\Cep\Services\CepService;
use JeffersonGoncalves\Cep\Support\CepSupport;

/**
 * @property string $cep
 * @property string|null $state
 * @property string|null $city
 * @property string|null $neighborhood
 * @property string|null $street
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep whereCep($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep whereNeighborhood($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cep whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
        $cep = self::sanitize($cep);

        if ($cep === null) {
            return CepSupport::getResult();
        }

        $model = self::query()->find($cep);

        if ($model !== null && ! $model->isStale()) {
            return $model->toArray();
        }

        return CepService::findByCep($cep);
    }

    /**
     * Look up a CEP using only the local database, without ever calling the
     * external providers (and therefore without writing to the database).
     */
    public static function findByCepInDatabase(?string $cep): array
    {
        $cep = self::sanitize($cep);

        if ($cep === null) {
            return CepSupport::getResult();
        }

        return self::query()->find($cep)?->toArray() ?? CepSupport::getResult();
    }

    /**
     * Normalize and validate a raw CEP string. Returns null when the value is
     * not a valid 8 digit numeric CEP, short-circuiting before any database or
     * HTTP lookup.
     */
    public static function sanitize(?string $cep): ?string
    {
        if (empty($cep)) {
            return null;
        }

        $cep = mb_substr(str_pad(str_replace(['.', '-', '/', '(', ')', ' '], '', $cep), 8, '0', STR_PAD_LEFT), 0, 8);

        if (! preg_match('/^\d{8}$/', $cep)) {
            return null;
        }

        return $cep;
    }

    public function isStale(): bool
    {
        $ttl = config('cep.cache_ttl');

        if ($ttl === null) {
            return false;
        }

        if ($this->updated_at === null) {
            return true;
        }

        return $this->updated_at->addSeconds((int) $ttl)->isPast();
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
