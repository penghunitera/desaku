<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReverseProxyPrefixTest extends TestCase
{
    public function test_loopback_proxy_keeps_login_redirect_under_deployment_prefix(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Prefix' => '/desaku'])
            ->get('/pengelola')
            ->assertRedirect('http://localhost/desaku/pengelola/masuk');
    }

    public function test_untrusted_client_cannot_supply_a_deployment_prefix(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders(['X-Forwarded-Prefix' => '/fake-prefix'])
            ->get('/pengelola')
            ->assertRedirect('http://localhost/pengelola/masuk');
    }

    public function test_loopback_proxy_keeps_public_links_under_deployment_prefix(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Prefix' => '/desaku'])
            ->get('/')
            ->assertOk()
            ->assertSee('http://localhost/desaku/layanan', false);
    }
}
