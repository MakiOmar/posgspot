<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\StorefrontCommunityPost;
use App\StorefrontCommunityPostMedia;
use App\StorefrontCommunityPostTranslation;
use App\Support\StorefrontLocale;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

/**
 * POS admin CRUD for storefront community posts (tournaments, events, news).
 */
class StorefrontCommunityPostController extends Controller
{
    public function __construct(private Util $commonUtil)
    {
    }

    public function index()
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $businessId = (int) request()->session()->get('user.business_id');
            $query = StorefrontCommunityPost::query()
                ->where('business_id', $businessId)
                ->with(['translations' => fn ($q) => $q->where('locale', StorefrontLocale::DEFAULT)])
                ->select('storefront_community_posts.*');

            if (request()->filled('type') && request('type') !== 'all') {
                $query->where('type', request('type'));
            }
            if (request()->filled('status') && request('status') !== 'all') {
                $query->where('status', request('status'));
            }

            return DataTables::of($query)
                ->addColumn('action', function ($row) {
                    $edit = action([self::class, 'edit'], [$row->id]);
                    $apps = action([StorefrontCommunityApplicationController::class, 'index'], ['post_id' => $row->id]);
                    $delete = action([self::class, 'destroy'], [$row->id]);
                    $html = '<a href="'.e($edit).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="glyphicon glyphicon-edit"></i> Edit</a> ';
                    if (in_array($row->type, [StorefrontCommunityPost::TYPE_TOURNAMENT, StorefrontCommunityPost::TYPE_EVENT], true)) {
                        $html .= '<a href="'.e($apps).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info"><i class="fa fa-users"></i> Applications</a> ';
                    }
                    $html .= '<button data-href="'.e($delete).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_community_post_button"><i class="glyphicon glyphicon-trash"></i> Delete</button>';

                    return $html;
                })
                ->addColumn('title', function ($row) {
                    $t = $row->translationFor(StorefrontLocale::DEFAULT);
                    $title = e($t?->title ?? '—');
                    if ($row->is_featured) {
                        $title .= ' <span class="label bg-blue">Featured</span>';
                    }

                    return $title;
                })
                ->editColumn('type', fn ($row) => e(ucfirst((string) $row->type)))
                ->editColumn('status', function ($row) {
                    $label = $row->status === StorefrontCommunityPost::STATUS_PUBLISHED ? 'bg-green' : 'bg-yellow';

                    return '<span class="label '.$label.'">'.e(ucfirst((string) $row->status)).'</span>';
                })
                ->addColumn('registration_mode', fn ($row) => e((string) ($row->registration_mode ?? 'off')))
                ->editColumn('starts_at', fn ($row) => $row->starts_at
                    ? $this->commonUtil->format_date($row->starts_at->toDateTimeString(), true)
                    : '—')
                ->editColumn('published_at', fn ($row) => $row->published_at
                    ? $this->commonUtil->format_date($row->published_at->toDateTimeString(), true)
                    : '—')
                ->rawColumns(['action', 'status', 'title'])
                ->make(true);
        }

        return view('storefront.community.index');
    }

    public function create()
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        return view('storefront.community.form', $this->formPayload(null));
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $validated = $this->validatePost($request);

        DB::transaction(function () use ($request, $businessId, $validated) {
            $slug = $this->uniqueSlug($businessId, $validated['slug'] ?? '', $validated['title_en'] ?? '');
            $cover = $this->handleCoverUpload($request, $businessId, null);
            $publishedAt = $this->resolvePublishedAt($validated, null);

            $post = StorefrontCommunityPost::create([
                'business_id' => $businessId,
                'type' => $validated['type'],
                'slug' => $slug,
                'status' => $validated['status'],
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
                'location_id' => $validated['location_id'] ?? null,
                'prize_pool' => $validated['prize_pool'] ?? null,
                'entry_fee' => $validated['entry_fee'] ?? null,
                'available_spots' => $validated['available_spots'] ?? null,
                'registration_mode' => $validated['registration_mode'] ?? StorefrontCommunityPost::REGISTRATION_OFF,
                'registration_url' => $validated['registration_url'] ?? null,
                'registration_open' => (bool) ($validated['registration_open'] ?? false),
                'winner' => $validated['winner'] ?? null,
                'cover_path' => $cover,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'published_at' => $publishedAt,
            ]);

            $this->syncTranslations($post, $validated);
            $this->syncMedia($request, $post, $businessId);
        });

        return redirect()
            ->action([self::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => 'Community post created.']);
    }

    public function edit(int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $post = StorefrontCommunityPost::where('business_id', $businessId)
            ->with(['translations', 'media'])
            ->findOrFail($id);

        return view('storefront.community.form', $this->formPayload($post));
    }

    public function update(Request $request, int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $post = StorefrontCommunityPost::where('business_id', $businessId)->findOrFail($id);
        $validated = $this->validatePost($request, $post->id);

        DB::transaction(function () use ($request, $businessId, $post, $validated) {
            $slug = $this->uniqueSlug(
                $businessId,
                $validated['slug'] ?? $post->slug,
                $validated['title_en'] ?? '',
                $post->id
            );
            $cover = $this->handleCoverUpload($request, $businessId, $post->cover_path);

            $post->fill([
                'type' => $validated['type'],
                'slug' => $slug,
                'status' => $validated['status'],
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
                'location_id' => $validated['location_id'] ?? null,
                'prize_pool' => $validated['prize_pool'] ?? null,
                'entry_fee' => $validated['entry_fee'] ?? null,
                'available_spots' => $validated['available_spots'] ?? null,
                'registration_mode' => $validated['registration_mode'] ?? StorefrontCommunityPost::REGISTRATION_OFF,
                'registration_url' => $validated['registration_url'] ?? null,
                'registration_open' => (bool) ($validated['registration_open'] ?? false),
                'winner' => $validated['winner'] ?? null,
                'cover_path' => $cover,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'published_at' => $this->resolvePublishedAt($validated, $post),
            ]);
            $post->save();
            $this->syncTranslations($post, $validated);
            $this->syncMedia($request, $post, $businessId);
        });

        return redirect()
            ->action([self::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => 'Community post updated.']);
    }

    public function destroy(int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $post = StorefrontCommunityPost::where('business_id', $businessId)->findOrFail($id);
        $post->delete();

        return response()->json(['success' => true, 'msg' => 'Post deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(?StorefrontCommunityPost $post): array
    {
        $businessId = (int) request()->session()->get('user.business_id');
        $locations = BusinessLocation::where('business_id', $businessId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return [
            'post' => $post,
            'translations' => $post ? $this->translationsMap($post) : $this->emptyTranslations(),
            'locations' => ['' => '— None —'] + $locations,
            'media' => $post ? $post->media : collect(),
            'action' => $post
                ? action([self::class, 'update'], [$post->id])
                : action([self::class, 'store']),
            'method' => $post ? 'put' : 'post',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvePublishedAt(array $validated, ?StorefrontCommunityPost $existing): ?\Carbon\Carbon
    {
        if ($validated['status'] === StorefrontCommunityPost::STATUS_DRAFT) {
            return null;
        }

        if (! empty($validated['published_at'])) {
            return \Carbon\Carbon::parse($validated['published_at']);
        }

        if ($existing?->published_at) {
            return $existing->published_at;
        }

        return now();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePost(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = 'nullable|string|max:191|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

        return $request->validate([
            'type' => ['required', 'string', Rule::in(StorefrontCommunityPost::TYPES)],
            'status' => ['required', 'string', Rule::in(StorefrontCommunityPost::STATUSES)],
            'slug' => $slugRule,
            'is_featured' => 'nullable|boolean',
            'location_id' => 'nullable|integer',
            'prize_pool' => 'nullable|string|max:191',
            'entry_fee' => 'nullable|string|max:191',
            'available_spots' => 'nullable|integer|min:0',
            'registration_mode' => ['nullable', 'string', Rule::in(StorefrontCommunityPost::REGISTRATION_MODES)],
            'registration_url' => 'nullable|string|max:500',
            'registration_open' => 'nullable|boolean',
            'winner' => 'nullable|string|max:191',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'published_at' => 'nullable|date',
            'cover' => 'nullable|image|max:4096',
            'remove_cover' => 'nullable|boolean',
            'gallery.*' => 'nullable|file|max:10240',
            'remove_media' => 'nullable|array',
            'remove_media.*' => 'integer',
            'title_en' => 'required|string|max:191',
            'excerpt_en' => 'nullable|string|max:500',
            'body_en' => 'nullable|string|max:65000',
            'game_title_en' => 'nullable|string|max:191',
            'rules_en' => 'nullable|string|max:65000',
            'results_en' => 'nullable|string|max:65000',
            'highlights_en' => 'nullable|string|max:65000',
            'recap_en' => 'nullable|string|max:65000',
            'registration_details_en' => 'nullable|string|max:5000',
            'title_ar' => 'nullable|string|max:191',
            'excerpt_ar' => 'nullable|string|max:500',
            'body_ar' => 'nullable|string|max:65000',
            'game_title_ar' => 'nullable|string|max:191',
            'rules_ar' => 'nullable|string|max:65000',
            'results_ar' => 'nullable|string|max:65000',
            'highlights_ar' => 'nullable|string|max:65000',
            'recap_ar' => 'nullable|string|max:65000',
            'registration_details_ar' => 'nullable|string|max:5000',
        ]);
    }

    private function uniqueSlug(int $businessId, string $slug, string $titleEn, ?int $ignoreId = null): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            $slug = Str::slug($titleEn);
        }
        if ($slug === '') {
            $slug = 'post-'.Str::random(6);
        }

        $base = $slug;
        $i = 0;
        while (StorefrontCommunityPost::where('business_id', $businessId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function handleCoverUpload(Request $request, int $businessId, ?string $existing): ?string
    {
        $dir = 'storefront_community/'.$businessId;
        $this->commonUtil->ensurePublicUploadPermissions($dir, null, true);

        if ($request->boolean('remove_cover')) {
            return null;
        }

        if ($request->hasFile('cover')) {
            $uploaded = $this->commonUtil->uploadFile($request, 'cover', $dir, 'image');

            return $uploaded ?: $existing;
        }

        return $existing;
    }

    private function syncMedia(Request $request, StorefrontCommunityPost $post, int $businessId): void
    {
        $remove = array_map('intval', (array) $request->input('remove_media', []));
        if ($remove !== []) {
            StorefrontCommunityPostMedia::where('community_post_id', $post->id)
                ->whereIn('id', $remove)
                ->delete();
        }

        if (! $request->hasFile('gallery')) {
            return;
        }

        $dir = 'storefront_community/'.$businessId;
        $this->commonUtil->ensurePublicUploadPermissions($dir, null, true);
        $sort = (int) StorefrontCommunityPostMedia::where('community_post_id', $post->id)->max('sort_order');

        foreach ($request->file('gallery', []) as $file) {
            if (! $file) {
                continue;
            }
            $mime = (string) $file->getMimeType();
            $kind = str_starts_with($mime, 'video/')
                ? StorefrontCommunityPostMedia::KIND_VIDEO
                : StorefrontCommunityPostMedia::KIND_IMAGE;
            $name = time().'_'.Str::random(6).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('uploads/'.$dir), $name);
            $sort++;
            StorefrontCommunityPostMedia::create([
                'community_post_id' => $post->id,
                'kind' => $kind,
                'path' => $name,
                'sort_order' => $sort,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncTranslations(StorefrontCommunityPost $post, array $validated): void
    {
        $locales = [
            StorefrontLocale::DEFAULT => [
                'title' => trim((string) ($validated['title_en'] ?? '')),
                'excerpt' => trim((string) ($validated['excerpt_en'] ?? '')) ?: null,
                'body' => trim((string) ($validated['body_en'] ?? '')) ?: null,
                'game_title' => trim((string) ($validated['game_title_en'] ?? '')) ?: null,
                'rules' => trim((string) ($validated['rules_en'] ?? '')) ?: null,
                'results' => trim((string) ($validated['results_en'] ?? '')) ?: null,
                'highlights' => trim((string) ($validated['highlights_en'] ?? '')) ?: null,
                'recap' => trim((string) ($validated['recap_en'] ?? '')) ?: null,
                'registration_details' => trim((string) ($validated['registration_details_en'] ?? '')) ?: null,
            ],
            'ar' => [
                'title' => trim((string) ($validated['title_ar'] ?? '')),
                'excerpt' => trim((string) ($validated['excerpt_ar'] ?? '')) ?: null,
                'body' => trim((string) ($validated['body_ar'] ?? '')) ?: null,
                'game_title' => trim((string) ($validated['game_title_ar'] ?? '')) ?: null,
                'rules' => trim((string) ($validated['rules_ar'] ?? '')) ?: null,
                'results' => trim((string) ($validated['results_ar'] ?? '')) ?: null,
                'highlights' => trim((string) ($validated['highlights_ar'] ?? '')) ?: null,
                'recap' => trim((string) ($validated['recap_ar'] ?? '')) ?: null,
                'registration_details' => trim((string) ($validated['registration_details_ar'] ?? '')) ?: null,
            ],
        ];

        foreach ($locales as $locale => $fields) {
            if ($locale === 'ar' && $fields['title'] === '') {
                StorefrontCommunityPostTranslation::where('community_post_id', $post->id)
                    ->where('locale', 'ar')
                    ->delete();

                continue;
            }

            StorefrontCommunityPostTranslation::updateOrCreate(
                ['community_post_id' => $post->id, 'locale' => $locale],
                $fields
            );
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function translationsMap(StorefrontCommunityPost $post): array
    {
        $map = $this->emptyTranslations();
        foreach ($post->translations as $row) {
            $map[$row->locale] = [
                'title' => (string) $row->title,
                'excerpt' => (string) ($row->excerpt ?? ''),
                'body' => (string) ($row->body ?? ''),
                'game_title' => (string) ($row->game_title ?? ''),
                'rules' => (string) ($row->rules ?? ''),
                'results' => (string) ($row->results ?? ''),
                'highlights' => (string) ($row->highlights ?? ''),
                'recap' => (string) ($row->recap ?? ''),
                'registration_details' => (string) ($row->registration_details ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function emptyTranslations(): array
    {
        $blank = [
            'title' => '',
            'excerpt' => '',
            'body' => '',
            'game_title' => '',
            'rules' => '',
            'results' => '',
            'highlights' => '',
            'recap' => '',
            'registration_details' => '',
        ];

        return [
            StorefrontLocale::DEFAULT => $blank,
            'ar' => $blank,
        ];
    }
}
