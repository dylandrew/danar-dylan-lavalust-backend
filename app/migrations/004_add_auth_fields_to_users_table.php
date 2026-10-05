<?php

class Add_auth_fields_to_users_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if (!$this->_lava->dbforge->table_exists('users')) {
            return;
        }

        if (!$this->_lava->dbforge->column_exists('users', 'password')) {
            $this->_lava->dbforge->add_column('users', [
                'password' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => FALSE,
                    'default'    => '',
                ],
            ]);
        }

        if (!$this->_lava->dbforge->column_exists('users', 'role')) {
            $this->_lava->dbforge->add_column('users', [
                'role' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => FALSE,
                    'default'    => 'user',
                ],
            ]);
        }

        if (!$this->_lava->dbforge->column_exists('users', 'is_active')) {
            $this->_lava->dbforge->add_column('users', [
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                    'default'    => 1,
                ],
            ]);
        }
    }

    public function down()
    {
    }
}