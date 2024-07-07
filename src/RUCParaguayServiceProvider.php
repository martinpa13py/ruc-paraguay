<?php 

namespace martinpa13py\RUCParaguay;
use Illuminate\Support\ServiceProvider;
use pabloacastillo\RUCParaguay\Services\RUCParaguay;
use Storage;
use GuzzleHttp\Client;


class RUCParaguayServiceProvider extends ServiceProvider{


	public function boot(){
		$this->commands([
			\pabloacastillo\RUCParaguay\Console\Commands\RucParaguayCmdUpdate::class,
			\pabloacastillo\RUCParaguay\Console\Commands\RucParaguayCmdSearch::class,
		]);

		$this->loadMigrationsFrom(__DIR__.'/database/migrations/');
	}

	public function register(){

		$this->app->bind('RUCParaguay', function () {
            return new RUCParaguay();
        });
	}



}