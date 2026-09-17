import { CommunityListScreen } from "../src/components/community/CommunityListScreen";

export default function GamingNewsScreen() {
  return (
    <CommunityListScreen
      type="news"
      detailBase="/gaming-news"
      titleKey="community.newsTitle"
      leadKey="community.newsLead"
    />
  );
}
