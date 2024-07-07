<?php 

namespace martinpa13py\RUCParaguay;
use Illuminate\Support\ServiceProvider;
use martinpa13py\RUCParaguay\Services\RUCParaguay;
use martinpa13py\RUCParaguay\Console\Commands\RucParaguayCmdSearch;
use martinpa13py\RUCParaguay\Console\Commands\RucParaguayCmdUpdate;


class RUCParaguayServiceProvider extends ServiceProvider{


	public function boot(){
		$this->commands([
			RucParaguayCmdUpdate::class,
			RucParaguayCmdSearch::class,
		]);

		$this->loadMigrationsFrom(__DIR__.'/database/migrations/');
	}

	public function register(){

		$this->app->bind('RUCParaguay', function () {
            return new RUCParaguay();
        });
	}



}