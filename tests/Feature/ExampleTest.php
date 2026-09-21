<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // الصفحة الرئيسية صارت موقعاً عاماً (SEO) بدل التحويل إلى تسجيل الدخول.
        $this->get('/')->assertOk();
    }
}
