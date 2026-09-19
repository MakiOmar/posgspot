import { fetchCommunityPost, withNetworkRetry } from "~/lib/api";
import {
  loadCommunitySidebarLists,
  type CommunitySidebarLists,
} from "~/lib/community-sidebar-data";
import type { CommunityPostDetail, CommunityPostType } from "~/lib/types";

export type CommunityDetailLoadResult = CommunitySidebarLists & {
  post: CommunityPostDetail | null;
  notFound: boolean;
};

/**
 * Load a community detail page payload with sidebar lists.
 * Transient API/network failures degrade to notFound (avoid q-data 500s).
 */
export async function loadCommunityDetailPage(
  locale: string,
  slug: string,
  expectedType: CommunityPostType,
): Promise<CommunityDetailLoadResult & { wrongType: boolean }> {
  const [sidebar, postResult] = await Promise.all([
    loadCommunitySidebarLists(locale),
    withNetworkRetry(() => fetchCommunityPost(slug, locale))
      .then((res) => ({ ok: true as const, data: res.data as CommunityPostDetail }))
      .catch((err: unknown) => ({ ok: false as const, err })),
  ]);

  if (!postResult.ok) {
    // Any fetch failure (404, 5xx, network) → empty detail; never throw (prevents q-data 500).
    return { post: null, notFound: true, wrongType: false, ...sidebar };
  }

  if (postResult.data.type !== expectedType) {
    return { post: postResult.data, notFound: false, wrongType: true, ...sidebar };
  }

  return { post: postResult.data, notFound: false, wrongType: false, ...sidebar };
}
