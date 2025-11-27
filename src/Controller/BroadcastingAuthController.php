<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Log\Log;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\Exception\BroadcastingException;
use Crustum\Broadcasting\Exception\InvalidChannelException;
use Exception;

/**
 * Broadcasting Auth Controller
 *
 * CakePHP controller that handles broadcasting authentication endpoints.
 *
 * @property \Authentication\Controller\Component\AuthenticationComponent $Authentication
 */
class BroadcastingAuthController extends Controller
{
    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
        $this->Authentication->allowUnauthenticated(['auth', 'userAuth']);
    }

    /**
     * Before filter method to set up controller-specific configurations
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event The event object
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->viewBuilder()->setClassName('Json');

        $this->Authentication->setConfig([
            'unauthenticatedRedirect' => null,
            'queryParam' => 'redirect',
        ]);
    }

    /**
     * Private/Presence channel authorization endpoint
     *
     * POST /broadcasting/auth
     *
     * @return \Cake\Http\Response
     */
    public function auth(): Response
    {
        if (!$this->request->is('post')) {
            return $this->errorResponse('Method not allowed. Only POST is supported.', 405);
        }

        try {
            $broadcaster = Broadcasting::get();
            $authData = $broadcaster->auth($this->request);

            $jsonData = json_encode($authData);
            if ($jsonData === false) {
                return $this->errorResponse('Invalid JSON data', 500);
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody($jsonData);
        } catch (InvalidChannelException $e) {
            Log::error($e->getMessage() . ' ' . $e->getTraceAsString());

            return $this->errorResponse($e->getMessage(), 403);
        } catch (BroadcastingException $e) {
            Log::error($e->getMessage() . ' ' . $e->getTraceAsString());

            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' ' . $e->getTraceAsString());

            return $this->errorResponse('Authentication failed', 500);
        }
    }

    /**
     * User authentication endpoint
     *
     * POST /broadcasting/user-auth
     *
     * @return \Cake\Http\Response
     */
    public function userAuth(): Response
    {
        if (!$this->request->is('post')) {
            return $this->errorResponse('Method not allowed. Only POST is supported.', 405);
        }

        try {
            $broadcaster = Broadcasting::get();
            $userData = $broadcaster->resolveAuthenticatedUser($this->request);

            if ($userData === null) {
                return $this->errorResponse('User not authenticated', 403);
            }

            $jsonData = json_encode($userData);
            if ($jsonData === false) {
                return $this->errorResponse('Invalid JSON data', 500);
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody($jsonData);
        } catch (BroadcastingException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (Exception $e) {
            return $this->errorResponse('User authentication failed', 500);
        }
    }

    /**
     * Create error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @return \Cake\Http\Response
     */
    private function errorResponse(string $message, int $code): Response
    {
        $jsonData = json_encode(['error' => $message]);
        if ($jsonData === false) {
            return $this->response
                ->withStatus($code)
                ->withType('text/plain')
                ->withStringBody('Error: ' . $message);
        }

        return $this->response
            ->withStatus($code)
            ->withType('application/json')
            ->withStringBody($jsonData);
    }
}
