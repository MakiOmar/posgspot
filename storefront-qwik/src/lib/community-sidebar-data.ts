import { ApiError, fetchCommunityPosts } from "~/lib/api";
import type { CommunityPostSummary } from "~/lib/types";

export type CommunitySidebarLists = {
  upcomingTournaments: CommunityPostSummary[];
  upcomingEvents: CommunityPostSummary[];
};

/** Upcoming tournament/event lists for the shared community sidebar. */
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
