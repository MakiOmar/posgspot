<?php

namespace App\Http\Controllers;

use App\StorefrontCommunityPost;
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
                    $delete = action([self::class, 'destroy'], [$row->id]);
                    $html = '<a href="'.e($edit).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="glyphicon glyphicon-edit"></i> Edit</a> ';
                    $html .= '<button data-href="'.e($delete).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_community_post_button"><i class="glyphicon glyphicon-trash"></i> Delete</button>';

                    return $html;
                })
                ->addColumn('title', function ($row) {
                    $t = $row->translationFor(StorefrontLocale::DEFAULT);

                    return e($t?->title ?? '—');
                })
                ->editColumn('type', fn ($row) => e(ucfirst((string) $row->type)))
                ->editColumn('status', function ($row) {
                    $label = $row->status === StorefrontCommunityPost::STATUS_PUBLISHED ? 'bg-green' : 'bg-yellow';

                    return '<span class="label '.$label.'">'.e(ucfirst((string) $row->status)).'</span>';
                })
                ->editColumn('starts_at', fn ($row) => $row->starts_at
                    ? $this->commonUtil->format_date($row->starts_at->toDateTimeString(), true)
                    : '—')
                ->editColumn('published_at', fn ($row) => $row->published_at
                    ? $this->commonUtil->format_date($row->published_at->toDateTimeString(), true)
                    : '—')
                ->rawColumns(['action', 'status'])
                ->make(true);
        }

        return view('storefront.community.index');
    }

    public function create()
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        return view('storefront.community.form', [
            'post' => null,
            'translations' => $this->emptyTranslations(),
            'action' => action([self::class, 'store']),
            'method' => 'post',
        ]);
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

            $post = StorefrontCommunityPost::create([
                'business_id' => $businessId,
                'type' => $validated['type'],
                'slug' => $slug,
                'status' => $validated['status'],
                'cover_path' => $cover,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'published_at' => $validated['status'] === StorefrontCommunityPost::STATUS_PUBLISHED ? now() : null,
            ]);

            $this->syncTranslations($post, $validated);
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
            ->with('translations')
            ->findOrFail($id);

        return view('storefront.community.form', [
            'post' => $post,
            'translations' => $this->translationsMap($post),
            'action' => action([self::class, 'update'], [$post->id]),
            'method' => 'put',
        ]);
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
            $wasPublished = $post->status === StorefrontCommunityPost::STATUS_PUBLISHED;

            $post->fill([
                'type' => $validated['type'],
                'slug' => $slug,
                'status' => $validated['status'],
                'cover_path' => $cover,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
            ]);

            if ($validated['status'] === StorefrontCommunityPost::STATUS_PUBLISHED && ! $wasPublished) {
                $post->published_at = now();
            } elseif ($validated['status'] === StorefrontCommunityPost::STATUS_DRAFT) {
                $post->published_at = null;
            }

            $post->save();
            $this->syncTranslations($post, $validated);
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
    private function validatePost(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = 'nullable|string|max:191|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

        return $request->validate([
            'type' => ['required', 'string', Rule::in(StorefrontCommunityPost::TYPES)],
            'status' => ['required', 'string', Rule::in(StorefrontCommunityPost::STATUSES)],
            'slug' => $slugRule,
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'cover' => 'nullable|image|max:4096',
            'remove_cover' => 'nullable|boolean',
            'title_en' => 'required|string|max:191',
            'excerpt_en' => 'nullable|string|max:500',
            'body_en' => 'nullable|string|max:65000',
            'title_ar' => 'nullable|string|max:191',
            'excerpt_ar' => 'nullable|string|max:500',
            'body_ar' => 'nullable|string|max:65000',
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
            ],
            'ar' => [
                'title' => trim((string) ($validated['title_ar'] ?? '')),
                'excerpt' => trim((string) ($validated['excerpt_ar'] ?? '')) ?: null,
                'body' => trim((string) ($validated['body_ar'] ?? '')) ?: null,
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
     * @return array<string, array{title: string, excerpt: string, body: string}>
     */
    private function translationsMap(StorefrontCommunityPost $post): array
    {
        $map = $this->emptyTranslations();
        foreach ($post->translations as $row) {
            $map[$row->locale] = [
                'title' => (string) $row->title,
                'excerpt' => (string) ($row->excerpt ?? ''),
                'body' => (string) ($row->body ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @return array<string, array{title: string, excerpt: string, body: string}>
     */
    private function emptyTranslations(): array
    {
        return [
            StorefrontLocale::DEFAULT => ['title' => '', 'excerpt' => '', 'body' => ''],
            'ar' => ['title' => '', 'excerpt' => '', 'body' => ''],
        ];
    }
}
