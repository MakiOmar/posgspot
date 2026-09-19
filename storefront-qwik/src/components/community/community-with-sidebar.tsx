import { component$, Slot } from "@builder.io/qwik";
import { CommunityNewsSidebar } from "~/components/community/community-news-sidebar";
import type { CommunityPostSummary } from "~/lib/types";

type Props = {
  upcomingTournaments?: CommunityPostSummary[];
  upcomingEvents?: CommunityPostSummary[];
  initialQuery?: string;
};

/**
 * Shared Community CMS chrome: main column + news sidebar
 * (newsletter, search, upcoming tournaments/events).
 */
export const CommunityWithSidebar = component$<Props>((props) => {
  return (
    <div class="community-with-sidebar__body content-page">
      <div class="community-with-sidebar__main">
        <Slot />
      </div>
      <CommunityNewsSidebar
        upcomingTournaments={props.upcomingTournaments ?? []}
        upcomingEvents={props.upcomingEvents ?? []}
        initialQuery={props.initialQuery}
      />
    </div>
  );
});
