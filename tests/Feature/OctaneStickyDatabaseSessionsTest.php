<?php

namespace Laravel\Vapor\Tests\Feature;

if (! interface_exists(\Laravel\Octane\Contracts\Client::class)) {
    return;
}

use Illuminate\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\MySqlConnection;
use Laravel\Vapor\Runtime\Octane\Octane;
use Laravel\Vapor\Tests\TestCase;
use Mockery;
use PDO;
use PDOStatement;
use ReflectionClass;

class OctaneStickyDatabaseSessionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDatabaseSessions([]);
    }

    protected function tearDown(): void
    {
        $this->setDatabaseSessions([]);

        parent::tearDown();
    }

    public function test_extending_the_session_does_not_send_sticky_reads_to_the_writer()
    {
        $writePdo = Mockery::mock(PDO::class);
        $writePdo->shouldReceive('exec')->once()->with('SET SESSION wait_timeout=28800')->andReturn(0);
        $writePdo->shouldReceive('prepare')->never()->andReturn($this->statement());

        $readPdo = Mockery::mock(PDO::class);
        $readPdo->shouldReceive('exec')->once()->with('SET SESSION wait_timeout=28800')->andReturn(0);
        $readPdo->shouldReceive('prepare')->once()->with('select 1')->andReturn($this->statement());

        $connection = $this->stickyConnection($writePdo, $readPdo);
        $connection->beforeExecuting($this->invoke('extendDatabaseSessions'));

        $connection->select('select 1');

        $this->assertFalse($connection->hasModifiedRecords());
        $this->assertSame($readPdo, $connection->getReadPdo());
    }

    public function test_extending_the_session_skips_pdos_that_are_not_open()
    {
        $writePdo = Mockery::mock(PDO::class);
        $writePdo->shouldNotReceive('exec');

        $readPdo = Mockery::mock(PDO::class);
        $readPdo->shouldNotReceive('exec');
        $readPdo->shouldReceive('prepare')->once()->andReturn($this->statement());

        $connection = $this->stickyConnection(function () use ($writePdo) {
            return $writePdo;
        }, function () use ($readPdo) {
            return $readPdo;
        });
        $connection->beforeExecuting($this->invoke('extendDatabaseSessions'));

        $connection->select('select 1');

        $this->assertFalse($connection->hasModifiedRecords());
    }

    public function test_the_session_ttl_is_shortened_on_both_pdos_after_the_request()
    {
        $writePdo = Mockery::mock(PDO::class);
        $writePdo->shouldReceive('exec')->once()->with('SET SESSION wait_timeout=10')->andReturn(0);

        $readPdo = Mockery::mock(PDO::class);
        $readPdo->shouldReceive('exec')->once()->with('SET SESSION wait_timeout=10')->andReturn(0);

        $connection = $this->stickyConnection($writePdo, $readPdo);

        $this->setDatabaseSessions(['mysql']);

        $db = Mockery::mock(DatabaseManager::class);
        $db->shouldReceive('getConnections')->andReturn(['mysql' => $connection]);

        $sandbox = new Container;
        $sandbox->instance('db', $db);

        call_user_func($this->invoke('manageDatabaseSessions', true, 10), null, null, $sandbox);

        $this->assertFalse($connection->hasModifiedRecords());
    }

    protected function stickyConnection($writePdo, $readPdo)
    {
        $connection = new MySqlConnection($writePdo, 'forge', '', [
            'name' => 'mysql',
            'driver' => 'mysql',
            'sticky' => true,
        ]);

        return $connection->setReadPdo($readPdo);
    }

    protected function statement()
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('setFetchMode', 'execute');
        $statement->shouldReceive('fetchAll')->andReturn([]);

        return $statement;
    }

    protected function invoke($method, ...$arguments)
    {
        $method = (new ReflectionClass(Octane::class))->getMethod($method);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    }

    protected function setDatabaseSessions(array $sessions)
    {
        $property = (new ReflectionClass(Octane::class))->getProperty('databaseSessions');
        $property->setAccessible(true);
        $property->setValue(null, $sessions);
    }
}
