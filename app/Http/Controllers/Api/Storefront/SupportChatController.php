<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Contact;
use App\Services\Storefront\SupportChatService;
use App\Services\Storefront\SupportEscalationService;
use App\Services\Storefront\TurnstileService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportChatController extends StorefrontController
{
    public function __construct(
        private SupportChatService $chat,
        private SupportEscalationService $escalation,
        private TurnstileService $turnstile,
    ) {
    }

    public function index(Request $request)
    {
        if ($denied = $this->guardEnabled()) {
            return $denied;
        }

        [$contact, $guestToken, $error] = $this->resolveIdentity($request, requireGuestToken: true);
        if ($error !== null) {
            return $error;
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));
        $result = $this->chat->listConversations(
            $this->businessId($request),
            $contact,
            $guestToken,
            $page,
            $perPage
        );

        return $this->jsonSuccess(['conversations' => $result['items']], $result['meta']);
    }

    public function store(Request $request)
    {
        if ($denied = $this->guardEnabled()) {
            return $denied;
        }

        [$contact, $guestToken, $error] = $this->resolveIdentity($request, requireGuestToken: true);
        if ($error !== null) {
            return $error;
        }

        $data = $request->validate([
            'locale' => 'nullable|string|in:en,ar',
        ]);

        $locale = $data['locale'] ?? StorefrontLocale::fromRequest($request);
        $conversation = $this->chat->startConversation(
            $this->businessId($request),
            $contact,
            $guestToken,
            $locale
        );

        return $this->jsonSuccess($this->chat->serializeConversation($conversation), [], 201);
    }

    public function show(Request $request, string $uuid)
    {
        if ($denied = $this->guardEnabled()) {
            return $denied;
        }

        [$contact, $guestToken, $error] = $this->resolveIdentity($request, requireGuestToken: true);
        if ($error !== null) {
            return $error;
        }

        $conversation = $this->chat->findForIdentity(
            $this->businessId($request),
            $uuid,
            $contact,
            $guestToken
        );
        if (! $conversation) {
            return $this->jsonError('Conversation not found.', 404);
        }

        return $this->jsonSuccess($this->chat->serializeConversation($conversation));
    }

    public function storeMessage(Request $request, string $uuid)
    {
        if ($denied = $this->guardEnabled()) {
            return $denied;
        }

        [$contact, $guestToken, $error] = $this->resolveIdentity($request, requireGuestToken: true);
        if ($error !== null) {
            return $error;
        }

        $data = $request->validate([
            'message' => 'required|string|max:4000',
            'locale' => 'nullable|string|in:en,ar',
            'page_context' => 'nullable|array',
            'page_context.path' => 'nullable|string|max:500',
            'page_context.product_slug' => 'nullable|string|max:191',
            'page_context.order_id' => 'nullable|integer',
            'turnstile_token' => 'nullable|string',
        ]);

        if (! $contact) {
            $turnstileError = $this->turnstile->validate(
                $this->businessId($request),
                $data['turnstile_token'] ?? null,
                $request->ip()
            );
            if ($turnstileError !== null) {
                return $this->jsonError($turnstileError, 422, ['turnstile_token' => [$turnstileError]]);
            }
        }

        $conversation = $this->chat->findForIdentity(
            $this->businessId($request),
            $uuid,
            $contact,
            $guestToken
        );
        if (! $conversation) {
            return $this->jsonError('Conversation not found.', 404);
        }

        try {
            $payload = $this->chat->sendMessage(
                $conversation,
                $data['message'],
                $contact,
                $data['locale'] ?? (string) $conversation->locale,
                $data['page_context'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), 409);
        }

        return $this->jsonSuccess($payload);
    }

    public function escalate(Request $request, string $uuid)
    {
        if ($denied = $this->guardEnabled()) {
            return $denied;
        }

        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();
        if (! $contact instanceof Contact) {
            return $this->jsonError('Sign in required to escalate.', 401);
        }

        $data = $request->validate([
            'note' => 'nullable|string|max:2000',
            'order_id' => 'nullable|integer',
        ]);

        $guestToken = $this->chat->normalizeGuestToken($request->header('X-Support-Guest-Token'));
        $conversation = $this->chat->findForIdentity(
            $this->businessId($request),
            $uuid,
            $contact,
            $guestToken
        );
        if (! $conversation) {
            return $this->jsonError('Conversation not found.', 404);
        }

        if ($conversation->escalation_id) {
            return $this->jsonError('Already escalated.', 409);
        }

        if (! $this->escalation->isConfigured($this->businessId($request))) {
            return $this->jsonError('Human escalation is not available yet. Please use WhatsApp or the contact form.', 503);
        }

        try {
            $escalation = $this->escalation->escalateFromConversation(
                $conversation,
                $contact,
                $data['note'] ?? null,
                isset($data['order_id']) ? (int) $data['order_id'] : null
            );
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), 503);
        }

        return $this->jsonSuccess([
            'reference_no' => $escalation->reference_no,
            'escalation_id' => $escalation->id,
            'conversation' => $this->chat->serializeConversation($conversation->fresh()),
            'message' => 'Your request was sent to the support team.',
        ]);
    }

    public function claim(Request $request)
    {
        if ($denied = $this->guardEnabled()) {
            return $denied;
        }

        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();
        if (! $contact instanceof Contact) {
            return $this->jsonError('Sign in required.', 401);
        }

        $guestToken = $this->chat->normalizeGuestToken($request->header('X-Support-Guest-Token'));
        $claimed = $this->chat->claimGuestConversations(
            $this->businessId($request),
            $contact->id,
            $guestToken
        );

        return $this->jsonSuccess(['claimed' => $claimed]);
    }

    private function guardEnabled()
    {
        if (! $this->chat->isEnabled()) {
            return $this->jsonError('Support chat is not available.', 503);
        }

        return null;
    }

    /**
     * @return array{0: ?Contact, 1: ?string, 2: mixed}
     */
    private function resolveIdentity(Request $request, bool $requireGuestToken): array
    {
        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();
        if ($contact && ! $contact instanceof Contact) {
            $contact = null;
        }

        $guestToken = $this->chat->normalizeGuestToken($request->header('X-Support-Guest-Token'));

        if (! $contact && $requireGuestToken && $guestToken === null) {
            return [null, null, $this->jsonError('X-Support-Guest-Token header required.', 422)];
        }

        return [$contact, $guestToken, null];
    }
}
