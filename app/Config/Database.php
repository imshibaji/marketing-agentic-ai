<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * The default database connection.
     *
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => '',
        'password'     => '',
        'database'     => '',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    //    /**
    //     * Sample database connection for SQLite3.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'database'    => 'database.db',
    //        'DBDriver'    => 'SQLite3',
    //        'DBPrefix'    => '',
    //        'DBDebug'     => true,
    //        'swapPre'     => '',
    //        'failover'    => [],
    //        'foreignKeys' => true,
    //        'busyTimeout' => 1000,
    //        'synchronous' => null,
    //        'dateFormat'  => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for Postgre.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => '',
    //        'hostname'   => 'localhost',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'database'   => 'ci4',
    //        'schema'     => 'public',
    //        'DBDriver'   => 'Postgre',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'utf8',
    //        'swapPre'    => '',
    //        'failover'   => [],
    //        'port'       => 5432,
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for SQLSRV.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => '',
    //        'hostname'   => 'localhost',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'database'   => 'ci4',
    //        'schema'     => 'dbo',
    //        'DBDriver'   => 'SQLSRV',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'utf8',
    //        'swapPre'    => '',
    //        'encrypt'    => false,
    //        'failover'   => [],
    //        'port'       => 1433,
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for OCI8.
    //     *
    //     * You may need the following environment variables:
    //     *   NLS_LANG                = 'AMERICAN_AMERICA.UTF8'
    //     *   NLS_DATE_FORMAT         = 'YYYY-MM-DD HH24:MI:SS'
    //     *   NLS_TIMESTAMP_FORMAT    = 'YYYY-MM-DD HH24:MI:SS'
    //     *   NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS'
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => 'localhost:1521/FREEPDB1',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'DBDriver'   => 'OCI8',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'AL32UTF8',
    //        'swapPre'    => '',
    //        'failover'   => [],
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    /**
     * This database connection is used when running PHPUnit database tests.
     *
     * @var array<string, mixed>
     */
    public array $tests = [
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => 'db_',  // Needed to ensure we're working correctly with prefixes live. DO NOT REMOVE FOR CI DEVS
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => '',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => true,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'synchronous' => null,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        $driver = strtolower(trim((string)getenv('DB_DRIVER') ?: 'sqlite'));
        
        if ($driver === 'mysql' || $driver === 'pdo_mysql') {
            $this->default['DBDriver'] = 'MySQLi';
            $this->default['hostname'] = getenv('DB_HOST') ?: '127.0.0.1';
            $this->default['port']     = (int)(getenv('DB_PORT') ?: 3306);
            $this->default['database'] = getenv('DB_DATABASE') ?: 'marketing_ai';
            $this->default['username'] = getenv('DB_USERNAME') ?: 'root';
            $this->default['password'] = getenv('DB_PASSWORD') ?: '';
            $this->default['charset']  = getenv('DB_CHARSET') ?: 'utf8mb4';
        } else if ($driver === 'pgsql' || $driver === 'postgresql') {
            $this->default['DBDriver'] = 'Postgre';
            $this->default['hostname'] = getenv('DB_HOST') ?: '127.0.0.1';
            $this->default['port']     = (int)(getenv('DB_PORT') ?: 5432);
            $this->default['database'] = getenv('DB_DATABASE') ?: '';
            $this->default['username'] = getenv('DB_USERNAME') ?: '';
            $this->default['password'] = getenv('DB_PASSWORD') ?: '';
            $this->default['charset']  = 'utf8';
        } else if ($driver === 'sqlsrv') {
            $this->default['DBDriver'] = 'SQLSRV';
            $this->default['hostname'] = getenv('DB_HOST') ?: '127.0.0.1';
            $this->default['port']     = (int)(getenv('DB_PORT') ?: 1433);
            $this->default['database'] = getenv('DB_DATABASE') ?: '';
            $this->default['username'] = getenv('DB_USERNAME') ?: '';
            $this->default['password'] = getenv('DB_PASSWORD') ?: '';
            $this->default['charset']  = 'utf8';
        } else if ($driver === 'oracle') {
            $this->default['DBDriver'] = 'OCI8';
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '1521';
            $serviceName = getenv('DB_SERVICE_NAME') ?: getenv('DB_DATABASE');
            $this->default['DSN']      = "{$host}:{$port}/{$serviceName}";
            $this->default['username'] = getenv('DB_USERNAME') ?: '';
            $this->default['password'] = getenv('DB_PASSWORD') ?: '';
            $this->default['charset']  = 'AL32UTF8';
        } else {
            // Default to SQLite3
            $sqliteFile = getenv('DB_SQLITE_FILE') ?: 'database/database.sqlite';
            if ($sqliteFile === '') {
                $sqliteFile = 'database/database.sqlite';
            }
            if (strpos($sqliteFile, '/') !== 0 && !preg_match('/^[A-Za-z]:\\\\/', $sqliteFile)) {
                $sqliteFile = WRITEPATH . '../../' . $sqliteFile;
            }
            
            $this->default = [
                'database'    => $sqliteFile,
                'DBDriver'    => 'SQLite3',
                'DBPrefix'    => '',
                'DBDebug'     => true,
                'swapPre'     => '',
                'failover'    => [],
                'foreignKeys' => true,
                'busyTimeout' => 1000,
                'synchronous' => null,
                'dateFormat'  => [
                    'date'     => 'Y-m-d',
                    'datetime' => 'Y-m-d H:i:s',
                    'time'     => 'H:i:s',
                ],
            ];
        }

        // Ensure that we always set the database group to 'tests' if
        // we are currently running an automated test suite, so that
        // we don't overwrite live data on accident.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }
    }
}
