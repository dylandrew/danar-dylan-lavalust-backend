<?php

class Migration
{
    public static $command = 'migration';
    public static $description = 'Run database migrations';
    public static $arguments = [
        '[action]' => 'run, create-migration, rollback, rollback-all, refresh, status',
        '[name]' => 'Migration class name for create-migration',
    ];

    protected static $route_map = [
        'run' => 'migrate',
        'create-migration' => 'create-migration',
        'rollback' => 'rollback',
        'rollback-all' => 'rollback-all',
        'refresh' => 'refresh',
        'status' => 'status',
    ];

    public function handle($action = null, array $flags = [], $name = null)
    {
        $action = $action ?? 'run';

        if (!isset(static::$route_map[$action])) {
            fwrite(STDERR, "Unknown migration action: \"{$action}\"\nAvailable actions: " . implode(', ', array_keys(static::$route_map)) . PHP_EOL);
            exit(1);
        }

        if ($action === 'create-migration') {
            if (!$name) {
                fwrite(STDERR, "Migration name is required. Example: php lava migration create-migration create_products_table" . PHP_EOL);
                exit(1);
            }
            $route = 'create-migration/' . $name;
        } else {
            $route = static::$route_map[$action];
        }

        $index = PUBLIC_DIR . 'index.php';
        if (!file_exists($index)) {
            fwrite(STDERR, "index.php not found at: {$index}" . PHP_EOL);
            exit(1);
        }

        $command = sprintf('%s %s %s', escapeshellarg(PHP_BINARY), escapeshellarg($index), escapeshellarg($route));
        passthru($command, $status);
        exit($status);
    }
}