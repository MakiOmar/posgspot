<?php

namespace Tests\Feature;

use App\Media;
use App\Services\SellDocumentService;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Multiple "Attach Documents" on sells / sales orders: upload validation, storage as media, delete endpoints.
 */
class SellDocumentsTest extends TestCase
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

    private function sell(): Transaction
    {
        $sell = Transaction::where('business_id', 1)->whereIn('type', ['sell', 'sales_order'])->first();
        if (! $sell) {
            $this->markTestSkipped('No sell transaction for business 1.');
        }

        return $sell;
    }

    private function requestWithFiles(array $files): Request
    {
        return Request::create('/sells', 'POST', [], [], [SellDocumentService::INPUT_NAME => $files]);
    }

    public function test_store_uploads_attaches_every_file_as_sell_document_media(): void
    {
        $this->actingAs($this->admin());
        $sell = $this->sell();
        $request = $this->requestWithFiles([
            UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ]);

        app(SellDocumentService::class)->storeUploads(1, $sell, $request);

        $names = app(SellDocumentService::class)->documentsFor($sell->fresh())->pluck('display_name')->all();
        $this->assertContains('contract.pdf', $names);
        $this->assertContains('receipt.pdf', $names);
    }

    public function test_validation_rejects_disallowed_type_and_too_many_files(): void
    {
        $service = app(SellDocumentService::class);

        try {
            $service->validateUploads($this->requestWithFiles([UploadedFile::fake()->create('shell.php', 1, 'text/x-php')]));
            $this->fail('Disallowed mime type should fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(SellDocumentService::INPUT_NAME . '.0', $e->errors());
        }

        $tooMany = [];
        for ($i = 0; $i <= SellDocumentService::MAX_FILES_PER_REQUEST; $i++) {
            $tooMany[] = UploadedFile::fake()->create("f{$i}.pdf", 1, 'application/pdf');
        }
        $this->expectException(ValidationException::class);
        $service->validateUploads($this->requestWithFiles($tooMany));
    }

    public function test_delete_endpoint_removes_only_this_sells_document(): void
    {
        $admin = $this->admin();
        $sell = $this->sell();
        $doc = $sell->media()->create(['file_name' => time() . '_1_doc.pdf', 'business_id' => 1, 'uploaded_by' => $admin->id, 'model_media_type' => SellDocumentService::MEDIA_TYPE]);
        $shipping = $sell->media()->create(['file_name' => time() . '_2_ship.pdf', 'business_id' => 1, 'uploaded_by' => $admin->id, 'model_media_type' => 'shipping_document']);

        $this->actingAs($admin)->withSession(['user.business_id' => 1])
            ->deleteJson(route('sells.documents.destroy', ['transaction_id' => $sell->id, 'media_id' => $doc->id]))
            ->assertOk()->assertJsonPath('success', true);
        $this->assertNull(Media::find($doc->id));

        $this->actingAs($admin)->withSession(['user.business_id' => 1])
            ->deleteJson(route('sells.documents.destroy', ['transaction_id' => $sell->id, 'media_id' => $shipping->id]))
            ->assertNotFound();
        $this->assertNotNull(Media::find($shipping->id));
    }

    public function test_sales_order_create_and_sell_edit_forms_render_multi_file_field(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/sells/create?sale_type=sales_order')
            ->assertOk()
            ->assertSee('name="sell_documents[]"', false)
            ->assertSee('js/sell-documents.js', false);

        // Sells with a return cannot be edited; widen the edit-days window (rolled back) so any sale opens.
        $sell = Transaction::where('business_id', 1)->where('type', 'sell')->where('status', 'final')
            ->whereNotExists(fn ($q) => $q->from('transactions as r')->whereColumn('r.return_parent_id', 'transactions.id'))
            ->latest('id')->first();
        if (! $sell) {
            $this->markTestSkipped('No editable sell for business 1.');
        }
        \App\Business::where('id', 1)->update(['transaction_edit_days' => 100000]);

        $this->actingAs($admin)->withSession([])->get('/sells/' . $sell->id . '/edit')
            ->assertOk()
            ->assertSee('name="sell_documents[]"', false)
            ->assertSee('sell-documents-saved', false);
    }

    public function test_legacy_delete_clears_single_document_column(): void
    {
        $admin = $this->admin();
        $sell = $this->sell();
        $sell->forceFill(['document' => time() . '_legacy.pdf'])->save();

        $this->actingAs($admin)->withSession(['user.business_id' => 1])
            ->deleteJson(route('sells.documents.destroy-legacy', ['transaction_id' => $sell->id]))
            ->assertOk()->assertJsonPath('success', true);

        $this->assertNull($sell->fresh()->document);
    }
}
