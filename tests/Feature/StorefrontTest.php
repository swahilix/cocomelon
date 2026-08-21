<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class StorefrontTest extends TestCase{use RefreshDatabase;public function test_home_page_is_available():void{$this->get('/')->assertOk();}public function test_tracking_page_is_available():void{$this->get('/track-order')->assertOk();}}
