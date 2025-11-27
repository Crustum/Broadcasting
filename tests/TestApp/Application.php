<?php
declare(strict_types=1);

namespace TestApp;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Identity;
use Authentication\Middleware\AuthenticationMiddleware;
use Cake\Core\Configure;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test Application class for Broadcasting Plugin testing
 *
 * This application is used for integration testing of the plugin controllers.
 */
class Application extends BaseApplication implements AuthenticationServiceProviderInterface
{
    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Crustum/Broadcasting', ['bootstrap' => true, 'routes' => true]);
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $authenticationService = new AuthenticationService();
        $authenticationService->loadAuthenticator('Authentication.Session');
        $middlewareQueue
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))
            ->add(new RoutingMiddleware($this))
            ->add(new AuthenticationMiddleware($authenticationService));

        return $middlewareQueue;
    }

    /**
     * Returns a service provider instance.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $authenticationService = new AuthenticationService([
            'unauthenticatedRedirect' => null,
            'queryParam' => null,
            'requireIdentity' => false,
        ]);

        $authenticationService->loadAuthenticator('Authentication.Session', [
            'sessionKey' => 'Auth',
            'identify' => false,
            'identifier' => [
                'Authentication.Callback' => [
                    'callback' => function ($data) {
                        if (is_array($data) && !empty($data)) {
                            return new Identity($data);
                        }

                        return null;
                    },
                ],
            ],
        ]);

        return $authenticationService;
    }
}
