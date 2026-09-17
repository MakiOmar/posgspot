import { CommunityListScreen } from "../../src/components/community/CommunityListScreen";

export default function TournamentsScreen() {
  return (
    <CommunityListScreen
      type="tournament"
      detailBase="/tournaments"
      titleKey="community.tournamentsTitle"
      leadKey="community.tournamentsLead"
      scoped
    />
  );
}
