<?php

namespace JeffersonGoncalves\Cep\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FlushCache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct() {}

    /**
     * @throws BindingResolutionException
     */
    public function handle(): void
    {
        $config = Container::getInstance()
            ->make('config')
            ->get('laravel-model-caching.store');

        Container::getInstance()
            ->make('cache')
            ->build($config)
            ->flush();
    }
}
