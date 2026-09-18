<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_trust_pages_are_publicly_available(): void
    {
        $this->get('/pages/faq')
            ->assertOk()
            ->assertSee('Frequently Asked Questions')
            ->assertSee('Can I order through WhatsApp?');

        $this->get('/pages/warranty')
            ->assertOk()
            ->assertSee('Warranty Information')
            ->assertSee('Product-specific coverage');

        $this->get('/pages/returns')
            ->assertOk()
            ->assertSee('Returns & Refunds')
            ->assertSee('Start a return request');

        $this->get('/pages/shipping')
            ->assertOk()
            ->assertSee('Delivery & Store Pickup');
    }
}
