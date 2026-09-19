<?php

namespace App\Http\Controllers;

use App\StorefrontCommunityApplication;
use App\StorefrontCommunityPost;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

/**
 * POS inbox for community tournament/event applications.
 */
class StorefrontCommunityApplicationController extends Controller
{
    public function __construct(private Util $commonUtil)
    {
    }

    public function index()
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $postId = request()->filled('post_id') ? (int) request('post_id') : null;
        $post = null;
        if ($postId) {
            $post = StorefrontCommunityPost::where('business_id', $businessId)->findOrFail($postId);
        }

        if (request()->ajax()) {
            $query = StorefrontCommunityApplication::query()
                ->where('business_id', $businessId)
                ->with(['post.translations'])
                ->select('storefront_community_applications.*');

            if ($postId) {
                $query->where('community_post_id', $postId);
            }
            if (request()->filled('status') && request('status') !== 'all') {
                $query->where('status', request('status'));
            }

            return DataTables::of($query)
                ->addColumn('post_title', function ($row) {
                    $t = $row->post?->translationFor('en');

                    return e($t?->title ?? ('#'.$row->community_post_id));
                })
                ->editColumn('status', function ($row) {
                    $label = match ($row->status) {
                        StorefrontCommunityApplication::STATUS_ACCEPTED => 'bg-green',
                        StorefrontCommunityApplication::STATUS_REJECTED => 'bg-red',
                        StorefrontCommunityApplication::STATUS_REVIEWED => 'bg-blue',
                        default => 'bg-yellow',
                    };

                    return '<span class="label '.$label.'">'.e(ucfirst((string) $row->status)).'</span>';
                })
                ->editColumn('created_at', fn ($row) => $this->commonUtil->format_date($row->created_at->toDateTimeString(), true))
                ->addColumn('action', function ($row) {
                    $url = action([self::class, 'updateStatus'], [$row->id]);
                    $html = '<select class="form-control input-sm community-app-status" data-href="'.e($url).'">';
                    foreach (StorefrontCommunityApplication::STATUSES as $status) {
                        $sel = $row->status === $status ? ' selected' : '';
                        $html .= '<option value="'.e($status).'"'.$sel.'>'.e(ucfirst($status)).'</option>';
                    }
                    $html .= '</select>';

                    return $html;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('storefront.community.applications', [
            'post' => $post,
            'postId' => $postId,
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $app = StorefrontCommunityApplication::where('business_id', $businessId)->findOrFail($id);
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(StorefrontCommunityApplication::STATUSES)],
        ]);
        $app->status = $data['status'];
        $app->save();

        return response()->json(['success' => true, 'msg' => 'Status updated.']);
    }

    public function export(): StreamedResponse
    {
        if (! auth()->user()->can('storefront.settings')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');
        $postId = request()->filled('post_id') ? (int) request('post_id') : null;

        $query = StorefrontCommunityApplication::query()
            ->where('business_id', $businessId)
            ->with(['post.translations'])
            ->orderByDesc('id');
        if ($postId) {
            $query->where('community_post_id', $postId);
        }

        $filename = 'community-applications-'.date('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'post', 'name', 'mobile', 'status', 'source', 'locale', 'created_at']);
            foreach ($query->cursor() as $row) {
                $title = $row->post?->translationFor('en')?->title ?? '';
                fputcsv($out, [
                    $row->id,
                    $title,
                    $row->name,
                    $row->mobile,
                    $row->status,
                    $row->source,
                    $row->locale,
                    optional($row->created_at)?->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
