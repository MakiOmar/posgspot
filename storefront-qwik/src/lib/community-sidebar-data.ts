import { ApiError, fetchCommunityPosts } from "~/lib/api";
import type { CommunityPostSummary, CommunityPostType } from "~/lib/types";

export type CommunitySidebarLists = {
  upcomingTournaments: CommunityPostSummary[];
  upcomingEvents: CommunityPostSummary[];
};

export type CommunityScopedListPage = CommunitySidebarLists & {
  posts: CommunityPostSummary[];
};

/**
 * Upcoming tournament/event lists for the shared community sidebar.
 * Fetches both scopes in parallel once — list pages should reuse the matching
 * array as `posts` instead of issuing a duplicate upcoming fetch.
 */
export async function loadCommunitySidebarLists(locale: string): Promise<CommunitySidebarLists> {
  try {
    const [tournaments, events] = await Promise.all([
      fetchCommunityPosts({ type: "tournament", scope: "upcoming" }, locale),
      fetchCommunityPosts({ type: "event", scope: "upcoming" }, locale),
    ]);
    return {
      upcomingTournaments: tournaments.data as CommunityPostSummary[],
      upcomingEvents: events.data as CommunityPostSummary[],
    };
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      return { upcomingTournaments: [], upcomingEvents: [] };
    }
    return { upcomingTournaments: [], upcomingEvents: [] };
  }
}

/**
 * Events/tournaments index: one sidebar fetch supplies both widgets and the
 * main upcoming list (no second identical upcoming API call).
 */
export async function loadCommunityScopedListPage(
  locale: string,
  listType: Extract<CommunityPostType, "event" | "tournament">,
): Promise<CommunityScopedListPage> {
  const sidebar = await loadCommunitySidebarLists(locale);
  const posts =
    listType === "event" ? sidebar.upcomingEvents : sidebar.upcomingTournaments;
  return { ...sidebar, posts };
}
