<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql')]
class ExistingDatabaseTest extends TestCase
{
    public function test_migrations_keep_existing_unprefixed_tables_and_data_intact(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Requires dedicated MySQL test database.');
        }
        $this->artisan('db:wipe', ['--force' => true])->assertSuccessful();
        DB::reconnect();
        $pdo = DB::connection()->getPdo();
        $pdo->exec('CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(80)) ENGINE=InnoDB');
        $pdo->exec("INSERT INTO users (id, name) VALUES (99, 'Legacy user')");
        $pdo->exec('CREATE TABLE meals (id BIGINT PRIMARY KEY, title VARCHAR(80)) ENGINE=InnoDB');
        $pdo->exec("INSERT INTO meals (id, title) VALUES (88, 'Legacy diary')");
        $pdo->exec('CREATE TABLE migrations (id BIGINT PRIMARY KEY, migration VARCHAR(255), batch INT) ENGINE=InnoDB');
        $pdo->exec("INSERT INTO migrations (id, migration, batch) VALUES (1, '0001_01_01_000000_create_users_table', 1)");
        try {
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $this->assertSame('Legacy user', $pdo->query('SELECT name FROM users WHERE id=99')->fetchColumn());
            $this->assertSame('Legacy diary', $pdo->query('SELECT title FROM meals WHERE id=88')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
            $this->assertSame(0, DB::table('users')->count());
            $this->assertSame('portal_', DB::connection()->getTablePrefix());
            $this->assertGreaterThan(0, DB::table('migrations')->count());
        } finally {
            $this->artisan('db:wipe', ['--force' => true]);
        }
    }
}
