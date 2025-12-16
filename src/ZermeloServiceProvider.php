<?php

namespace ftrotter\ZZZermelo;

use ftrotter\ZZZermelo\Console\MakeGraphReportCommand;
use ftrotter\ZZZermelo\Console\MakeTabularReportCommand;
use ftrotter\ZZZermelo\Console\ZZermeloDebugCommand;
use ftrotter\ZZZermelo\Console\ZZermeloInstallCommand;
use ftrotter\ZZZermelo\Console\MakeCardsReportCommand;
use ftrotter\ZZZermelo\Console\ZZermeloReportCheckCommand;
use ftrotter\ZZZermelo\Models\ZZermeloDatabase;
use ftrotter\ZZZermelo\Services\SocketService;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Class ZZermeloServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /*
     * Registration happens before boot, so this is where we gather static configuration
     * and register things to be used later.
     */
	public function register()
	{
        require_once __DIR__ . '/helpers.php';

        /*
         * Register our zermelo view make command which:
         *  - Copies views
         *  - Exports configuration
         *  - Exports Assets
         */
        $this->commands([
            ZZermeloInstallCommand::class,
            ZZermeloDebugCommand::class,
	    ZZermeloReportCheckCommand::class,
            MakeTabularReportCommand::class,
            MakeCardsReportCommand::class,
            MakeGraphReportCommand::class
        ]);

        /*
         * Merge with main config so parameters are accessable.
         * Try to load config from the app's config directory first,
         * then load from the package.
         */
        if ( file_exists(  config_path( 'zermelo.php' ) ) ) {
            $this->mergeConfigFrom(
                config_path( 'zermelo.php' ), 'zermelo'
            );
        } else {
            $this->mergeConfigFrom(
                __DIR__.'/../config/zermelo.php', 'zermelo'
            );
        }
	}//end register function..


	public $is_socket_ok = false; //start assuming it is not.
	public $is_socket_checked = false;
    /**
     * @param Router $router
     *
     * This function is called after all providers have been registered,
     * and the database hass been set up.
     */
    public function boot( Router $router )
    {
        if (php_sapi_name() !== 'cli') {
            // Register the cache database connection if we have a zermelo db,
            // but only if we're running a web route, not during install commands
            $zermelo_cache_db = zermelo_cache_db();
            if (ZZermeloDatabase::doesDatabaseExist($zermelo_cache_db)) {
                ZZermeloDatabase::configure($zermelo_cache_db);
            }

            // Register and configure the config DB
            $zermelo_config_db = zermelo_config_db();
            if ( ZZermeloDatabase::doesDatabaseExist( $zermelo_config_db ) ) {
                ZZermeloDatabase::configure( $zermelo_config_db );
            }

            // Validate that there is only one is_default_socket for a wrench, throw an exception
            // if there is a wrench with Zero default sockets, or a wrench with more than one
            // default socket, as this can result unexpected behavior
            if (!$this->is_socket_checked) {
                $this->is_socket_ok = SocketService::checkIsDefaultSocket();
                $this->is_socket_checked = true;
            }
        }

        // routes

        // Boot our reports, but only in web mode. We don't care to register reports
        // during composer package discovery, or installation
        if (php_sapi_name() !== 'cli') {
            $this->registerApiRoutes();
            $this->registerWebRoutes();
            $this->registerReports();
            $this->loadViewsFrom( resource_path( 'views/zermelo' ), 'ZZermelo');
        }
    }

    /**
     * Register the application's ZZermelo reports.
     *
     * @return void
     */
    protected function registerReports()
    {
        $reportDir = report_path();
        if ( File::isDirectory($reportDir) ) {
            ZZermelo::reportsIn( $reportDir );
        }
    }

    /**
     * Load the given routes file if routes are not already cached.
     *
     * @param  string  $path
     * @return void
     */
    protected function loadRoutesFrom($path)
    {
        if (! $this->app->routesAreCached()) {
            require $path;
        }
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerApiRoutes()
    {
        Route::group( $this->routeConfiguration(), function () {

            // Load the core zermelo api routes including sockets
            $this->loadRoutesFrom(__DIR__.'/../routes/api.sockets.php');

            $tabular_api_prefix = config('zermelo.TABULAR_API_PREFIX','ZZermelo');
            Route::group( ['prefix' => $tabular_api_prefix ], function() {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.tabular.php');
            });

            $graph_api_prefix = config('zermelo.GRAPH_API_PREFIX','ZZermeloGraph');
            Route::group( ['prefix' => $graph_api_prefix ], function() {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.graph.php');
            });

            $tree_api_prefix = config('zermelo.TREE_API_PREFIX','ZZermeloTree');
            Route::group( ['prefix' => $tree_api_prefix ], function() {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.tree.php');
            });
        });
    }

    /**
     * Get the WEB route configurations
     */
    protected function registerWebRoutes()
    {
        Route::group($this->webRouteConfiguration(), function() {

            // Load the pretty-print SQL routes from web.sql.php using the configured prefix
            $sql_print_prefix = config( 'zermelo.SQL_PRINT_PREFIX','ZZermeloSQL' );
            Route::group([ 'prefix' => $sql_print_prefix ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.sql.php');
            });

            Route::group([ 'prefix' => config('zermelo.CARD_URI_PREFIX', 'ZZermeloCard') ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.card.php');
            });

            Route::group([ 'prefix' => config('zermelo.GRAPH_URI_PREFIX', 'ZZermeloGraph') ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.graph.php');
            });

            Route::group([ 'prefix' => config('zermelo.TABULAR_URI_PREFIX', 'ZZermelo') ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.tabular.php');
            });

            Route::group([ 'prefix' => config('zermelo.TREECARD_URI_PREFIX', 'ZZermeloTreeCard') ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.tree_card.php');
            });
        });
    }

    /**
     * Get the API route group configuration array.
     *
     * @return array
     */
    protected function routeConfiguration()
    {
        $middleware = config('zermelo.MIDDLEWARE',[ 'api' ]);

        return [
            'namespace' => 'ftrotter\ZZZermelo\Http\Controllers',
            'domain' => config('zermelo.domain', null),
            'as' => 'zermelo.api.',
            'prefix' => config( 'zermelo.API_PREFIX', 'zapi' ),
            'middleware' => $middleware,
        ];
    }

    /**
     * Get the web route group configuration array.
     *
     * @return array
     */
    protected function webRouteConfiguration()
    {
        $middleware = config('zermelo.WEB_MIDDLEWARE',[ 'web' ]);

        return [
            'namespace' => 'ftrotter\ZZZermelo\Http\Controllers',
            //  'domain' => config('zermelo.domain', null),
            'as' => 'zermelo.web.',
            'prefix' => '',
            'middleware' => $middleware,
        ];
    }
}
