<?php

namespace Laravel\Vapor\Runtime\Handlers;

use Illuminate\Http\Request;
use Illuminate\Container\Container;
use Laravel\Vapor\Runtime\HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Psr\Http\Message\ResponseInterface;
use Laravel\Vapor\Contracts\LambdaEventHandler;
use Laravel\Vapor\Runtime\Http\PsrRequestFactory;
use Laravel\Vapor\Runtime\PsrLambdaResponseFactory;
use Illuminate\Console\Application as ConsoleApplication;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;

class AppHandler implements LambdaEventHandler
{
    /**
     * Handle an incoming Lambda event.
     *
     * @param  array  $event
     * @param  \Laravel\Vapor\Contracts\LambdaResponse
     */
    public function handle(array $event)
    {
        try {
            $app = require $_ENV['LAMBDA_TASK_ROOT'].'/bootstrap/app.php';

            $response = (new HttpKernel($app))->handle(Request::createFromBase(
                (new HttpFoundationFactory)->createRequest($this->marshalRequest($event))
            ));

            return $this->marshalResponse(
                (new DiactorosFactory)->createResponse($response)
            );
        } finally {
            if (isset($app)) {
                $this->terminate($app);

                $app = null;
            }
        }
    }

    /**
     * Create a new PSR-7 compliant request from the incoming event.
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function marshalRequest(array $event)
    {
        return (new PsrRequestFactory($event))->__invoke();
    }

    /**
     * Marshal the PSR-7 response to a Lambda response.
     *
     * @param  Psr\Http\Message\ResponseInterface  $response
     * @return \Laravel\Vapor\Runtime\ArrayLambdaResponse
     */
    protected function marshalResponse(ResponseInterface $response)
    {
        return PsrLambdaResponseFactory::fromPsrResponse($response);
    }

    /**
     * Terminate any relevant application services.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function terminate(Application $app)
    {
        if ($app->resolved('db')) {
            collect($app->make('db')->getConnections())->each->disconnect();
        }

        Application::setInstance(null);
        Container::setInstance(null);
        Facade::clearResolvedInstances();

        ConsoleApplication::forgetBootstrappers();

        $app->flush();
    }
}
