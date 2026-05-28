<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAllTables extends Migration
{
    public function up()
    {
        // 1. plans table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'campaign_limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 10,
            ],
            'lead_limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 50,
            ],
            'llm_limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 100,
            ],
            'email_limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 100,
            ],
            'whatsapp_limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 100,
            ],
            'sms_limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 100,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('plans', true);

        // 2. users table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'user',
                'null'       => false,
            ],
            'full_name' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'email' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'mobile' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'whatsapp_number' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'plan_campaigns' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 10,
            ],
            'plan_leads' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 50,
            ],
            'plan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'llm_usage' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'email_usage' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'whatsapp_usage' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'sms_usage' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users', true);

        // 3. campaigns table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'product_description' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'target_audience' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'channel' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'CREATED',
                'null'       => false,
            ],
            'final_content' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'crawl_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'none',
            ],
            'crawl_target' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => '',
            ],
            'language' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'English',
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'llm_provider' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'gemini',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('campaigns', true);

        // 4. campaign_shares table
        $this->forge->addField([
            'campaign_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
        ]);
        $this->forge->addUniqueKey(['campaign_id', 'user_id']);
        $this->forge->createTable('campaign_shares', true);

        // 5. leads table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'campaign_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'company_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'contact_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'whatsapp' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'mobile' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'industry' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'score' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'reasoning' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'email_draft' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'whatsapp_draft' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'sms_draft' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'GENERATED',
                'null'       => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'source' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'agent',
                'null'       => true,
            ],
            'postal_address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('leads', true);

        // 6. agent_logs table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'campaign_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'agent_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'log_text' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('agent_logs', true);

        // 7. user_activity_logs table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'details' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('user_activity_logs', true);

        // 8. email_otps table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'otp' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'used' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('email_otps', true);

        // 9. settings table
        $this->forge->addField([
            'key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('key', true);
        $this->forge->createTable('settings', true);

        // 10. notifications table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'sender_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'message' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('notifications', true);

        // 11. public_chats table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            'message' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('public_chats', true);
    }

    public function down()
    {
        $tables = [
            'public_chats', 'notifications', 'settings', 'email_otps',
            'user_activity_logs', 'agent_logs', 'leads', 'campaign_shares',
            'campaigns', 'users', 'plans'
        ];
        foreach ($tables as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
