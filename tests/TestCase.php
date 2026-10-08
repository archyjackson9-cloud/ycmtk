<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel's HTTP test client does not automatically carry a response's
     * session cookie into the next `$this->get()/post()` call the way a
     * real browser would - each call is independent unless cookies are
     * passed explicitly. Guest flows that span more than one request (add
     * to cart, then checkout, keyed by the session id) need the session to
     * survive between calls, so tests exercising those flows call this
     * after each response: `$this->carryCookies($response);`. Every
     * following call in the test then carries it automatically, since
     * `call()` merges `$this->defaultCookies` into every request.
     *
     * Note this reads the *plain* session id straight off the still-bound
     * session store rather than the already-encrypted Set-Cookie header on
     * $response - `defaultCookies` values get encrypted again by
     * `prepareCookiesForRequest()` before the next request, so feeding it
     * an already-encrypted value would double-encrypt it, fail decryption
     * server-side, and silently start a brand new session instead (the bug
     * this method exists to avoid). $response is still required so call
     * sites read naturally and so a future response-cookie-based cookie
     * (e.g. a "remember me" token) could be folded in here too.
     */
    protected function carryCookies(TestResponse $response): static
    {
        $this->defaultCookies[config('session.cookie')] = $this->app['session']->getId();

        return $this;
    }
}
