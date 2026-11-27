<?php
declare(strict_types=1);

/**
 * MDRRMO Response Emergency System
 * PostgreSQL connection helper.
 *
 * Requires DATABASE_URL in Render environment variables.
 * Example: postgresql://user:pass@host:5432/dbname?sslmode=require
 */

if (!function_exists('parseDatabaseUrl')) {
    /**
     * Read and validate DATABASE_URL, returning the connection parts.
     *
     * @return array{host:string, port:int, user:string, pass:string, name:string, sslmode:string}
     * @throws RuntimeException
     */
    function parseDatabaseUrl(): array
    {
        $databaseUrl = getenv('DATABASE_URL');

        if (!$databaseUrl) {
            error_log('MDRRMO: DATABASE_URL is not configured.');
            throw new RuntimeException('Database configuration is unavailable.');
        }

        $db = parse_url($databaseUrl);

        if (
            $db === false
            || empty($db['host'])
            || empty($db['user'])
            || !isset($db['pass'])
            || empty($db['path'])
            || $db['path'] === '/'
        ) {
            error_log('MDRRMO: Invalid DATABASE_URL.');
            throw new RuntimeException('Database configuration is invalid.');
        }

        $query = [];
        parse_str($db['query'] ?? '', $query);

        return [
            'host'    => $db['host'],
            'port'    => (int) ($db['port'] ?? 5432),
            'user'    => rawurldecode($db['user']),
            'pass'    => rawurldecode($db['pass']),
            'name'    => rawurldecode(ltrim($db['path'], '/')),
            'sslmode' => $query['sslmode'] ?? (getenv('PGSSLMODE') ?: 'require'),
        ];
    }
}

if (!function_exists('getDatabaseConnection')) {
    /**
     * Return a shared PDO connection (created once per request).
     *
     * @throws RuntimeException
     */
    function getDatabaseConnection(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $config = parseDatabaseUrl();

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['sslmode']
        );

        try {
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 10,
            ]);

            return $pdo;
        } catch (PDOException $e) {
            error_log('MDRRMO PostgreSQL connection failed: ' . $e->getMessage());

            throw new RuntimeException('Database connection failed.', 0, $e);
        }
    }
}