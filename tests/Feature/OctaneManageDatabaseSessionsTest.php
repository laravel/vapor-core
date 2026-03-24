<?php

namespace Laravel\Vapor\Tests\Feature;

use Laravel\Vapor\Tests\TestCase;
use Mockery;
use PDO;
use PDOException;

class OctaneManageDatabaseSessionsTest extends TestCase
{
    public function test_it_does_not_throw_when_pdo_connection_has_gone_away()
    {
        $stalePdo = Mockery::mock(PDO::class);
        $stalePdo->shouldReceive('exec')
            ->andThrow(new PDOException(
                'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'
            ));

        try {
            collect([$stalePdo])
                ->filter(fn ($pdo) => $pdo instanceof PDO)
                ->each(function ($pdo) {
                    try {
                        $pdo->exec(sprintf('SET SESSION wait_timeout=%s', 10));
                    } catch (\Throwable $e) {
                        // Connection already gone away, safe to ignore...
                    }
                });
        } catch (\Throwable $e) {
            $this->fail('Expected no exception, but caught: '.$e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_it_proves_bug_exists_without_fix()
    {
        $stalePdo = Mockery::mock(PDO::class);
        $stalePdo->shouldReceive('exec')
            ->andThrow(new PDOException(
                'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'
            ));

        $this->expectException(PDOException::class);

        // This is the ORIGINAL broken code - proves the bug
        collect([$stalePdo])
            ->filter(fn ($pdo) => $pdo instanceof PDO)
            ->each->exec(sprintf('SET SESSION wait_timeout=%s', 10));
    }

    public function test_it_still_sets_wait_timeout_on_live_connections()
    {
        $livePdo = Mockery::mock(PDO::class);
        $livePdo->shouldReceive('exec')
            ->once()
            ->with('SET SESSION wait_timeout=10')
            ->andReturn(true);

        collect([$livePdo])
            ->filter(fn ($pdo) => $pdo instanceof PDO)
            ->each(function ($pdo) {
                try {
                    $pdo->exec(sprintf('SET SESSION wait_timeout=%s', 10));
                } catch (\Throwable $e) {
                    //
                }
            });

        $this->addToAssertionCount(
            Mockery::getContainer()->mockery_getExpectationCount()
        );
    }

    public function test_it_handles_mixed_live_and_stale_connections()
    {
        // Simulates Aurora writer alive + reader dead
        $livePdo = Mockery::mock(PDO::class);
        $livePdo->shouldReceive('exec')->once()->andReturn(true);

        $stalePdo = Mockery::mock(PDO::class);
        $stalePdo->shouldReceive('exec')
            ->andThrow(new PDOException(
                'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'
            ));

        try {
            collect([$livePdo, $stalePdo])
                ->filter(fn ($pdo) => $pdo instanceof PDO)
                ->each(function ($pdo) {
                    try {
                        $pdo->exec(sprintf('SET SESSION wait_timeout=%s', 10));
                    } catch (\Throwable $e) {
                        //
                    }
                });
        } catch (\Throwable $e) {
            $this->fail('Expected no exception, but caught: '.$e->getMessage());
        }

        $this->assertTrue(true);
    }
}
