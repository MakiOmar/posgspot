<?php

namespace Tests\Feature\Storefront;

use Tests\TestCase;

class RepairStatusApiTest extends TestCase
{
    public function test_settings_exposes_repair_flags(): void
    {
        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'repair' => ['lookup_enabled', 'lookup_by_mobile'],
                ],
            ]);
    }

    public function test_lookup_requires_fields(): void
    {
        $this->postJson('/api/storefront/v1/repair/status', [])
            ->assertStatus(422);
    }

    public function test_lookup_returns_not_found_for_unknown_job_sheet(): void
    {
        $response = $this->postJson('/api/storefront/v1/repair/status', [
            'search_type' => 'job_sheet_no',
            'search_number' => 'DOES-NOT-EXIST-'.uniqid(),
        ]);

        // 404 when module is available; 503 when repair module/table is not.
        $this->assertContains($response->status(), [404, 503]);
        $response->assertJsonPath('success', false);
    }
}
