<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class MigrationController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->assert_migration_access();
        $this->call->library('migration');
    }

    public function create_migration($migration_class)
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $migration_class)) {
            show_error('Invalid migration name.', 'Bad Request', 'error_general', 400);
        }
        $this->migration->create_migration($migration_class);
    }

    public function migrate()
    {
        $this->migration->migrate();
    }

    public function rollback()
    {
        $this->migration->rollback();
    }

    public function rollback_all()
    {
        $this->migration->rollback_all();
    }

    public function refresh()
    {
        $this->migration->refresh();
    }

    public function status()
    {
        $this->migration->status();
    }

    private function assert_migration_access()
    {
        $http_enabled = config_item('environment') === 'development'
            && strtolower((string) getenv('ALLOW_HTTP_MIGRATIONS')) === 'true';

        if (!IS_CLI && !$http_enabled) {
            show_error('Migration routes are disabled over HTTP. Use the LavaLust CLI.', 'Forbidden', 'error_general', 403);
        }
    }
}