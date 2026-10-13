<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_presents_simare_services(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Urusan desa sampai kabupaten')
            ->assertSee('Kesehatan Masyarakat')
            ->assertSee('PBB &amp; BPHTB', false)
            ->assertSee('BUMDes Simare')
            ->assertSee('Firman Hutapea');
    }

    public function test_architecture_page_is_not_publicly_available(): void
    {
        $this->get('/arsitektur')
            ->assertNotFound();
    }
}
