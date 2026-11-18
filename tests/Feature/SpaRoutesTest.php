<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Laravel serves the React single-page app for browser URLs, while /api stays JSON.
 */
class SpaRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_landing_page_is_served_at_the_root(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<div id="root"></div>', false)
            ->assertSee(config('app.name'));
    }

    public function test_deep_links_are_served_by_the_spa_shell(): void
    {
        foreach (['/login', '/register', '/app', '/app/projects/42', '/app/admin/tenants'] as $path) {
            $this->get($path)->assertOk()->assertSee('<div id="root"></div>', false);
        }
    }

    public function test_unknown_api_routes_return_json_not_the_spa(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_health_check_is_not_captured_by_the_spa(): void
    {
        $this->get('/up')->assertOk()->assertDontSee('<div id="root"></div>', false);
    }
}
