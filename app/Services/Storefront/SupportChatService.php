<?php

namespace App\Services\Storefront;

use App\Contact;
use App\StorefrontSupport\SupportConversation;
use App\StorefrontSupport\SupportMessage;
use App\Support\StorefrontLocale;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Persist conversations and drive OpenAI chat + tools for storefront support.
 */
class SupportChatService
{
    private const MAX_HISTORY_MESSAGES = 24;

    private const MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private SupportToolRunner $tools,
        private StorefrontSettingService $settings,
        private SupportEscalationService $escalation,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('storefront.support_chat.enabled', false)
            && filled(config('openai.api_key'));
    }

    /**
     * Attach guest conversations to a contact after login.
     */
    public function claimGuestConversations(int $businessId, int $contactId, ?string $guestToken): int
    {
        $guestToken = $this->normalizeGuestToken($guestToken);
        if ($guestToken === null) {
            return 0;
        }

        return SupportConversation::query()
            ->where('business_id', $businessId)
            ->where('guest_token', $guestToken)
            ->whereNull('contact_id')
            ->update(['contact_id' => $contactId]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listConversations(
        int $businessId,
        ?Contact $contact,
        ?string $guestToken,
        int $page = 1,
        int $perPage = 20
    ): array {
        $query = SupportConversation::query()
            ->where('business_id', $businessId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->scopeIdentity($query, $contact, $guestToken);

        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $conversation) {
            /** @var SupportConversation $conversation */
            $preview = $conversation->messages()->orderByDesc('id')->value('content');
            $items[] = [
                'uuid' => $conversation->uuid,
                'title' => $conversation->title,
                'status' => $conversation->status,
                'locale' => $conversation->locale,
                'escalation_id' => $conversation->escalation_id,
                'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
                'preview' => $preview ? Str::limit(strip_tags((string) $preview), 120) : null,
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function startConversation(
        int $businessId,
        ?Contact $contact,
        ?string $guestToken,
        string $locale
    ): SupportConversation {
        $guestToken = $this->normalizeGuestToken($guestToken);
        if (! $contact && $guestToken === null) {
            throw new \InvalidArgumentException('Guest token required.');
        }

        $locale = in_array($locale, StorefrontLocale::SUPPORTED, true) ? $locale : StorefrontLocale::DEFAULT;

        // Keep prior active threads in history as closed when starting a new one.
        $closeQuery = SupportConversation::query()
            ->where('business_id', $businessId)
            ->where('status', 'active');
        $this->scopeIdentity($closeQuery, $contact, $guestToken);
        $closeQuery->update(['status' => 'closed']);

        return SupportConversation::create([
            'business_id' => $businessId,
            'uuid' => (string) Str::uuid(),
            'contact_id' => $contact?->id,
            'guest_token' => $contact ? null : $guestToken,
            'locale' => $locale,
            'status' => 'active',
            'title' => null,
            'last_message_at' => now(),
        ]);
    }

    public function findForIdentity(
        int $businessId,
        string $uuid,
        ?Contact $contact,
        ?string $guestToken
    ): ?SupportConversation {
        $query = SupportConversation::query()
            ->where('business_id', $businessId)
            ->where('uuid', $uuid);
        $this->scopeIdentity($query, $contact, $guestToken);

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeConversation(SupportConversation $conversation): array
    {
        $messages = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(static function (SupportMessage $m) {
                return [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'meta' => $m->meta,
                    'created_at' => optional($m->created_at)?->toIso8601String(),
                ];
            })
            ->all();

        return [
            'uuid' => $conversation->uuid,
            'title' => $conversation->title,
            'status' => $conversation->status,
            'locale' => $conversation->locale,
            'escalation_id' => $conversation->escalation_id,
            'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
            'messages' => $messages,
            'escalation_available' => $conversation->contact_id
                && $this->escalation->isConfigured((int) $conversation->business_id)
                && ! $conversation->escalation_id,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $pageContext
     * @return array<string, mixed>
     */
    public function sendMessage(
        SupportConversation $conversation,
        string $userMessage,
        ?Contact $contact,
        string $locale,
        ?array $pageContext = null
    ): array {
        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            throw new \InvalidArgumentException('Message required.');
        }

        if ($conversation->status === 'closed' && $conversation->escalation_id) {
            throw new \RuntimeException('This conversation was escalated. Start a new chat.');
        }

        if ($locale && in_array($locale, StorefrontLocale::SUPPORTED, true)) {
            $conversation->locale = $locale;
        }

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'meta' => $pageContext ? ['page_context' => $pageContext] : null,
            'created_at' => now(),
        ]);

        if (! $conversation->title) {
            $conversation->title = Str::limit($userMessage, 80);
        }
        $conversation->status = 'active';
        $conversation->last_message_at = now();
        $conversation->save();

        $assistant = $this->runModel($conversation, $contact);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $assistant['content'],
            'meta' => $assistant['meta'] ?: null,
            'created_at' => now(),
        ]);

        $conversation->last_message_at = now();
        $conversation->save();

        return $this->serializeConversation($conversation->fresh());
    }

    /**
     * @return array{content: string, meta: array<string, mixed>}
     */
    private function runModel(SupportConversation $conversation, ?Contact $contact): array
    {
        $locale = (string) $conversation->locale;
        $businessId = (int) $conversation->business_id;
        $authenticated = $contact !== null;

        $history = $conversation->messages()
            ->orderByDesc('id')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($businessId, $locale, $authenticated)],
        ];

        foreach ($history as $row) {
            if (! in_array($row->role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = [
                'role' => $row->role,
                'content' => (string) $row->content,
            ];
        }

        $tools = $this->tools->toolDefinitions($authenticated);
        $model = (string) config('storefront.support_chat.model', 'gpt-4o-mini');
        $usedTools = [];

        try {
            for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
                $payload = [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.4,
                ];
                if ($tools !== []) {
                    $payload['tools'] = $tools;
                    $payload['tool_choice'] = 'auto';
                }

                $response = OpenAI::chat()->create($payload);
                $choice = $response->choices[0]->message ?? null;
                if ($choice === null) {
                    break;
                }

                $toolCalls = $choice->toolCalls ?? [];
                if ($toolCalls === [] || $toolCalls === null) {
                    $content = trim((string) ($choice->content ?? ''));
                    if ($content === '') {
                        $content = $locale === 'ar'
                            ? 'معلش، مقدرتش أجاوب دلوقتي. جرّب تاني أو تواصل مع الدعم.'
                            : 'Sorry, I could not answer just now. Please try again or contact support.';
                    }

                    return [
                        'content' => $content,
                        'meta' => $usedTools !== [] ? ['tools' => $usedTools] : [],
                    ];
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $choice->content,
                    'tool_calls' => array_map(static function ($call) {
                        return [
                            'id' => $call->id,
                            'type' => 'function',
                            'function' => [
                                'name' => $call->function->name,
                                'arguments' => $call->function->arguments,
                            ],
                        ];
                    }, $toolCalls),
                ];

                foreach ($toolCalls as $call) {
                    $name = (string) $call->function->name;
                    $args = json_decode((string) $call->function->arguments, true);
                    if (! is_array($args)) {
                        $args = [];
                    }
                    $result = $this->tools->run($name, $args, $businessId, $contact, $locale);
                    $usedTools[] = $name;
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call->id,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Storefront support chat OpenAI failed.', [
                'conversation' => $conversation->uuid,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return [
                'content' => $locale === 'ar'
                    ? 'حصلت مشكلة مؤقتة في المساعد. استخدم واتساب أو نموذج اتصل بنا.'
                    : 'The assistant hit a temporary error. Please use WhatsApp or the contact form.',
                'meta' => ['error' => true],
            ];
        }

        return [
            'content' => $locale === 'ar'
                ? 'محتاج تفاصيل أكتر عشان أساعدك — قولي رقم الطلب أو وصف المشكلة.'
                : 'I need a bit more detail to help — share an order number or describe the issue.',
            'meta' => $usedTools !== [] ? ['tools' => $usedTools] : [],
        ];
    }

    private function systemPrompt(int $businessId, string $locale, bool $authenticated): string
    {
        $channels = $this->tools->run('get_contact_channels', [], $businessId, null, $locale);
        $faqHint = $locale === 'ar'
            ? 'جاوب بالعامية المصرية المهذبة والقصيرة. لو العميل كتب إنجليزي جاوب إنجليزي.'
            : 'Reply in clear concise English unless the user writes Arabic (then use Egyptian Arabic).';

        $authLine = $authenticated
            ? 'The customer is signed in. You may use order/repair/device tools. Never reveal another customer\'s data.'
            : 'The customer is a guest. Do not invent order status. Ask them to sign in for order/repair lookups, or guide them to /repair-status and /track-console.';

        $escalateLine = $authenticated && $this->escalation->isConfigured($businessId)
            ? 'If they insist on a human or need refunds/disputes, tell them to use the Escalate button (or offer handoff channels).'
            : 'For human help, direct them to WhatsApp, hotline, or /contact. Guests cannot create CRM escalations.';

        return implode("\n", [
            'You are the Games Spot storefront support assistant for Egypt gaming retail (consoles, games, digital codes, repairs).',
            $faqHint,
            $authLine,
            $escalateLine,
            'Never invent prices, stock, payment success, or order/repair status without tool results.',
            'Digital codes are usually non-refundable after reveal. Trade-in values require in-store inspection.',
            'End with one clear next step when possible.',
            'Handoff channels JSON: '.json_encode($channels, JSON_UNESCAPED_UNICODE),
            'Useful paths: /faq /contact /stores /account/orders /repair-status /track-console',
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\StorefrontSupport\SupportConversation>  $query
     */
    private function scopeIdentity($query, ?Contact $contact, ?string $guestToken): void
    {
        $guestToken = $this->normalizeGuestToken($guestToken);

        if ($contact) {
            $query->where(function ($q) use ($contact, $guestToken) {
                $q->where('contact_id', $contact->id);
                if ($guestToken !== null) {
                    $q->orWhere(function ($q2) use ($guestToken) {
                        $q2->whereNull('contact_id')->where('guest_token', $guestToken);
                    });
                }
            });

            return;
        }

        $query->whereNull('contact_id')->where('guest_token', $guestToken);
    }

    public function normalizeGuestToken(?string $token): ?string
    {
        $token = strtolower(trim((string) $token));
        if ($token === '' || ! Str::isUuid($token)) {
            return null;
        }

        return $token;
    }
}
