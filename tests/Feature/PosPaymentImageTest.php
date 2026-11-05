<?php

namespace Tests\Feature;

use App\Services\SellDocumentService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * POS Multiple Pay image: uploaded on its own route, then only the stored filename is accepted on the sale.
 */
class PosPaymentImageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        $user = User::where('business_id', 1)->where('allow_login', 1)->whereNotNull('username')->first();
        if (! $user) {
            $this->markTestSkipped('No login user for business 1.');
        }
        config(['constants.administrator_usernames' => $user->username]);

        return $user;
    }

    public function test_upload_stores_image_and_returns_filename_and_url(): void
    {
        $response = $this->actingAs($this->admin())->withSession(['user.business_id' => 1])
            ->post(route('sells.pos.upload-image'), [
                SellDocumentService::POS_IMAGE_INPUT => UploadedFile::fake()->image('receipt.png', 40, 40),
            ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('success', true);
        $filename = $response->json('filename');

        $this->assertStringEndsWith('receipt.png', $filename);
        $this->assertStringContainsString('uploads/documents/', $response->json('url'));
        Storage::disk('local')->assertExists(SellDocumentService::DOCUMENTS_DIR . '/' . $filename);
    }

    public function test_upload_rejects_non_image_file(): void
    {
        $this->actingAs($this->admin())->withSession(['user.business_id' => 1])
            ->post(route('sells.pos.upload-image'), [
                SellDocumentService::POS_IMAGE_INPUT => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_upload_requires_authentication(): void
    {
        $this->post(route('sells.pos.upload-image'), [
            SellDocumentService::POS_IMAGE_INPUT => UploadedFile::fake()->image('receipt.png'),
        ])->assertRedirect();
    }

    public function test_resolve_accepts_only_stored_image_basenames(): void
    {
        Storage::disk('local')->put(SellDocumentService::DOCUMENTS_DIR . '/123_photo.jpg', 'x');
        Storage::disk('local')->put(SellDocumentService::DOCUMENTS_DIR . '/123_contract.pdf', 'x');
        $service = app(SellDocumentService::class);

        $this->assertSame('123_photo.jpg', $service->resolveUploadedImageName('123_photo.jpg'));
        $this->assertNull($service->resolveUploadedImageName('123_contract.pdf'));
        $this->assertNull($service->resolveUploadedImageName('missing.png'));
        $this->assertNull($service->resolveUploadedImageName('../documents/123_photo.jpg'));
        $this->assertNull($service->resolveUploadedImageName('sub\\123_photo.jpg'));
        $this->assertNull($service->resolveUploadedImageName(''));
        $this->assertNull($service->resolveUploadedImageName(['123_photo.jpg']));
    }
}
