<?php

namespace Tests\Feature;

use Tests\TestCase;

class OfflineModeTest extends TestCase
{
    public function test_offline_page_renders_without_a_session_so_the_service_worker_can_store_it(): void
    {
        $response = $this->get('/offline');

        $response->assertOk();
        $response->assertSee('Tus canciones descargadas');
    }

    public function test_offline_page_does_not_load_livewire_because_it_has_to_work_without_the_server(): void
    {
        $response = $this->get('/offline');

        $response->assertOk();
        $response->assertDontSee('livewire.js', false);
        $response->assertDontSee('wire:snapshot', false);
    }
}
