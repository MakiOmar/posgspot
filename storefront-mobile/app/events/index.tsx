import { CommunityListScreen } from "../src/components/community/CommunityListScreen";

export default function EventsScreen() {
  return (
    <CommunityListScreen
      type="event"
      detailBase="/events"
      titleKey="community.eventsTitle"
      leadKey="community.eventsLead"
      scoped
    />
  );
}
