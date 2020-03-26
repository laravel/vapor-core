<?php

namespace Laravel\Vapor\Runtime\Fpm;

use Exception;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\SocketConnections\UnixDomainSocket;
use Symfony\Component\Process\Process;
use Throwable;

class Fpm
{
    public const SOCKET = '/tmp/.vapor/php-fpm.sock';
    public const CONFIG = '/tmp/.vapor/php-fpm.conf';
    public const PID_FILE = '/tmp/.vapor/php-fpm.pid';

    /**
     * The static FPM instance for the container.
     *
     * @var static
     */
    protected static $instance;

    /**
     * The file that should be invoked by FPM.
     *
     * @var string
     */
    protected $handler;

    /**
     * The additional server variables that should be passed to FPM.
     *
     * @var array
     */
    protected $serverVariables = [];

    /**
     * The FPM socket client instance.
     *
     * @var \Hoa\Socket\Client
     */
    protected $client;

    /**
     * The FPM socket connection instance.
     *
     * @var \Hoa\FastCGI\SocketConnections\UnixDomainSocket
     */
    protected $socketConnection;

    /**
     * The FPM process instance.
     *
     * @var \Symfony\Component\Process\Process
     */
    protected $fpm;

    /**
     * Create a new FPM instance.
     *
     * @param  \Hoa\Socket\Client  $handler
     * @param  \Hoa\FastCGI\SocketConnections\UnixDomainSocket  $socketConnection
     * @param  string  $handler
     * @param  array  $serverVariables
     * @return void
     */
    public function __construct(Client $client, UnixDomainSocket $socketConnection, string $handler, array $serverVariables = [])
    {
        $this->client = $client;
        $this->handler = $handler;
        $this->serverVariables = $serverVariables;
        $this->socketConnection = $socketConnection;
    }

    /**
     * Boot FPM with the given handler.
     *
     * @param  string  $handler
     * @param  array  $serverVariables
     * @return static
     */
    public static function boot($handler, array $serverVariables = [])
    {
        if (file_exists(static::SOCKET)) {
            @unlink(static::SOCKET);
        }

        $socketConnection = new UnixDomainSocket(self::SOCKET, 1000, 900000);

        return static::$instance = tap(new static(new Client, $socketConnection, $handler, $serverVariables), function ($fpm) {
            $fpm->start();
        });
    }

    /**
     * Resolve the static FPM instance.
     *
     * @return static
     */
    public static function resolve()
    {
        return static::$instance;
    }

    /**
     * Start the PHP-FPM process.
     */
    public function start()
    {
        if ($this->isReady()) {
            $this->killExistingFpm();
        }

        fwrite(STDERR, 'Ensuring ready to start FPM');

        $this->ensureReadyToStart();

        $this->fpm = new Process([
            'php-fpm',
            '--nodaemonize',
            '--force-stderr',
            '--fpm-config',
            self::CONFIG
        ]);

        fwrite(STDERR, 'Starting FPM Process...');

        $this->fpm->disableOutput()
            ->setTimeout(null)
            ->start(function ($type, $output) {
                fwrite(STDERR, $output);
            });

        $this->ensureFpmHasStarted();
    }

    /**
     * Ensure that the proper configuration is in place to start FPM.
     *
     * @return void
     */
    protected function ensureReadyToStart()
    {
        if (! is_dir(dirname(self::SOCKET))) {
            mkdir(dirname(self::SOCKET));
        }

        if (! file_exists(self::CONFIG)) {
            file_put_contents(
                self::CONFIG,
                file_get_contents(__DIR__.'/../../../stubs/php-fpm.conf')
            );
        }
    }

    /**
     * Proxy the request to PHP-FPM and return its response.
     *
     * @param  \Laravel\Vapor\Runtime\Fpm\FpmRequest  $request
     * @return \Laravel\Vapor\Runtime\Fpm\FpmResponse
     */
    public function handle($request)
    {
        return (new FpmApplication($this->client, $this->socketConnection))
                    ->handle($request);
    }

    /**
     * Wait until the FPM process is ready to receive requests.
     *
     * @return void
     */
    protected function ensureFpmHasStarted()
    {
        $elapsed = 0;

        while (! $this->isReady()) {
            usleep(5000);

            $elapsed += 5000;

            if ($elapsed > ($fiveSeconds = 5000000)) {
                throw new Exception('Timed out waiting for FPM to start: ' . self::SOCKET);
            }

            if (! $this->fpm->isRunning()) {
                throw new Exception('PHP-FPM was unable to start.');
            }
        }
    }

    /**
     * Determine is the FPM process is ready to receive requests.
     *
     * @return bool
     */
    protected function isReady()
    {
        clearstatcache(false, self::SOCKET);

        return file_exists(self::SOCKET);
    }

    /**
     * Ensure that the FPM process is still running.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function ensureRunning()
    {
        try {
            if (! $this->fpm || ! $this->fpm->isRunning()) {
                throw new Exception('PHP-FPM has stopped unexpectedly.');
            }
        } catch (Throwable $e) {
            echo $e->getMessage();

            exit(1);
        }
    }

    /**
     * Stop the FPM process.
     *
     * @return void
     */
    public function stop()
    {
        if ($this->fpm && $this->fpm->isRunning()) {
            $this->fpm->stop();
        }
    }

    /**
     * Kill any existing FPM processes on the system.
     *
     * @return void
     */
    protected function killExistingFpm()
    {
        fwrite(STDERR, 'Killing existing FPM');

        if (! file_exists(static::PID_FILE)) {
            return unlink(static::SOCKET);
        }

        $pid = (int) file_get_contents(static::PID_FILE);

        if (posix_getpgid($pid) === false) {
            return $this->removeFpmProcessFiles();
        }

        $result = posix_kill($pid, SIGTERM);

        if ($result === false) {
            return $this->removeFpmProcessFiles();
        }

        $this->waitUntilStopped($pid);

        $this->removeFpmProcessFiles();
    }

    /**
     * Remove FPM's process related files.
     *
     * @return void
     */
    protected function removeFpmProcessFiles()
    {
        unlink(static::SOCKET);
        unlink(static::PID_FILE);
    }

    /**
     * Wait until the given process is stopped.
     *
     * @param  int  $pid
     * @return void
     */
    protected function waitUntilStopped($pid)
    {
        $elapsed = 0;

        while (posix_getpgid($pid) !== false) {
            usleep(5000);

            $elapsed += 5000;

            if ($elapsed > 1000000) {
                throw new Exception("Process did not stop within the given threshold.");
            }
        }
    }

    /**
     * Get the underlying process.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function process()
    {
        return $this->fpm;
    }

    /**
     * Get the handler.
     *
     * @return string
     */
    public function handler()
    {
        return $this->handler;
    }

    /**
     * Get the server variables.
     *
     * @return array
     */
    public function serverVariables()
    {
        return $this->serverVariables;
    }

    /**
     * Handle the destruction of the class.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->stop();
    }
}
