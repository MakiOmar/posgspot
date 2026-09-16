<?php

namespace Tests\Feature\Storefront;

use App\Contact;
use App\StorefrontSupport\SupportConversation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    protected int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.support_chat.enabled' => true,
            'openai.api_key' => 'sk-test-support-chat',
        ]);
    }

    public function test_support_chat_unavailable_when_disabled(): void
    {
        config(['storefront.support_chat.enabled' => false]);

        $this->withHeader('X-Support-Guest-Token', (string) Str::uuid())
            ->getJson('/api/storefront/v1/support/conversations')
            ->assertStatus(503);
    }

    public function test_settings_exposes_support_chat_flag(): void
    {
        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.support_chat.enabled', true);

        config(['storefront.support_chat.enabled' => false]);

        $this->getJson('/api/storefront/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.support_chat.enabled', false);
    }

    public function test_guest_requires_token_header(): void
    {
        $this->getJson('/api/storefront/v1/support/conversations')
            ->assertStatus(422);
    }

    public function test_guest_can_create_list_and_message(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'أهلاً بيك، أقدر أساعدك في الطلبات والصيانة.',
                        ],
                    ],
                ],
            ]),
        ]);

        $guest = (string) Str::uuid();

        $create = $this->withHeader('X-Support-Guest-Token', $guest)
            ->postJson('/api/storefront/v1/support/conversations', ['locale' => 'ar'])
            ->assertCreated();

        $uuid = $create->json('data.uuid');
        $this->assertNotEmpty($uuid);

        $this->withHeader('X-Support-Guest-Token', $guest)
            ->postJson("/api/storefront/v1/support/conversations/{$uuid}/messages", [
                'message' => 'فين طلبي؟',
                'locale' => 'ar',
            ])
            ->assertOk()
            ->assertJsonPath('data.messages.0.role', 'user')
            ->assertJsonPath('data.messages.1.role', 'assistant');

        $list = $this->withHeader('X-Support-Guest-Token', $guest)
            ->getJson('/api/storefront/v1/support/conversations')
            ->assertOk();

        $this->assertNotEmpty($list->json('data.conversations'));
        $this->assertSame($uuid, $list->json('data.conversations.0.uuid'));
    }

    public function test_auth_cannot_read_other_guest_conversation(): void
    {
        $guestA = (string) Str::uuid();
        $guestB = (string) Str::uuid();

        $create = $this->withHeader('X-Support-Guest-Token', $guestA)
            ->postJson('/api/storefront/v1/support/conversations', ['locale' => 'en'])
            ->assertCreated();

        $uuid = $create->json('data.uuid');

        $this->withHeader('X-Support-Guest-Token', $guestB)
            ->getJson("/api/storefront/v1/support/conversations/{$uuid}")
            ->assertNotFound();
    }

    public function test_login_claims_guest_conversations(): void
    {
        $guest = (string) Str::uuid();
        $email = 'support_claim_'.uniqid().'@example.com';

        $create = $this->withHeader('X-Support-Guest-Token', $guest)
            ->postJson('/api/storefront/v1/support/conversations', ['locale' => 'en'])
            ->assertCreated();
        $uuid = $create->json('data.uuid');

        $contact = Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => 'Support Claim',
            'first_name' => 'Support',
            'email' => $email,
            'mobile' => '',
            'password' => Hash::make('password123'),
            'created_by' => 1,
            'email_verified_at' => now(),
        ]);

        $this->withHeader('X-Support-Guest-Token', $guest)
            ->postJson('/api/storefront/v1/auth/login', [
                'login' => $email,
                'password' => 'password123',
            ])
            ->assertOk();

        $conversation = SupportConversation::where('uuid', $uuid)->first();
        $this->assertNotNull($conversation);
        $this->assertSame($contact->id, (int) $conversation->contact_id);
    }

    public function test_escalate_requires_auth(): void
    {
        $guest = (string) Str::uuid();
        $create = $this->withHeader('X-Support-Guest-Token', $guest)
            ->postJson('/api/storefront/v1/support/conversations', ['locale' => 'en'])
            ->assertCreated();
        $uuid = $create->json('data.uuid');

        $this->withHeader('X-Support-Guest-Token', $guest)
            ->postJson("/api/storefront/v1/support/conversations/{$uuid}/escalate")
            ->assertStatus(401);
    }
}
