<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_the_login_page_loads(): void
    {
        $this->get(route('filament.admin.auth.login'))->assertOk();
    }
}
