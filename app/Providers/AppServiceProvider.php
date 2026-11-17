<?php

namespace App\Providers;

use App\Database\Oracle\OracleConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Yajra\Oci8\Connectors\OracleConnector as Connector;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        App::setLocale('id');
        URL::forceScheme('https');

        Connection::resolverFor('oracle', function ($connection, $database, $prefix, $config) {
            if (! empty($config['dynamic'])) {
                call_user_func_array($config['dynamic'], [&$config]);
            }

            $connector = new Connector;
            $connection = $connector->connect($config);
            $db = new OracleConnection($connection, $database, $prefix, $config);

            if (! empty($config['skip_session_vars'])) {
                return $db;
            }

            // set oracle session variables
            $sessionVars = [
                'NLS_TIME_FORMAT' => 'HH24:MI:SS',
                'NLS_DATE_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_FORMAT' => 'YYYY-MM-DD HH24:MI:SS',
                'NLS_TIMESTAMP_TZ_FORMAT' => 'YYYY-MM-DD HH24:MI:SS TZH:TZM',
                'NLS_NUMERIC_CHARACTERS' => '.,',
                ...($config['sessionVars'] ?? []),
            ];

            if (isset($config['schema'])) {
                $sessionVars['CURRENT_SCHEMA'] = $config['schema'];
            }

            if (isset($config['edition'])) {
                $sessionVars['EDITION'] = $config['edition'];
            }

            $db->setSessionVars($sessionVars);

            return $db;
        });
    }
}
