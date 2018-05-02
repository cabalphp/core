<?php
namespace Cabal\Core\Http\Middleware;

use Cabal\Core\Http\Request;
use Cabal\Core\Server;
use Cabal\Core\Session;
use Cabal\Core\SessionHandler\RedisSessionHandler;

class EnableSession
{
    public function handle(Server $server, Request $request, $vars, $next, $middlewareArgs = [])
    {
        $config = $server->configure('cabal.session', []);

        $sessionHandler = new RedisSessionHandler($server->cache());
        $sessionId = $request->cookie(isset($config['name']) ? $config['name'] : 'session_id');
        if (!$sessionId || strlen($sessionId) != 32) {
            $sessionId = $sessionHandler->create_sid();
        }
        $session = new Session(
            $sessionHandler,
            $sessionId,
            $config
        );

        $request = $request->withAttribute('session', $session);

        /**
         * @var \Cabal\Core\Http\Response $response
         */
        $response = $next($server, $request, $vars);

        $session->write();
        return $response->withCookie(
            $session->config('name'),
            $session->sessionId(),
            intval($session->config('cookie_lifetime')),
            $session->config('cookie_path'),
            $session->config('cookie_domain'),
            $session->config('cookie_secure'),
            $session->config('cookie_httponly')
        );

    }
}