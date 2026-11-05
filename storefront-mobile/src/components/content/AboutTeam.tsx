import FontAwesome from "@expo/vector-icons/FontAwesome";
import { Linking, Pressable, StyleSheet, Text, View } from "react-native";
import { useRtl } from "../../lib/rtl";
import type { AboutTeamMember } from "../../lib/types";
import { RemoteImage } from "../RemoteImage";

const SOCIAL_ICONS = {
  facebook: "facebook",
  instagram: "instagram",
  x: "twitter",
  youtube: "youtube-play",
  tiktok: "music",
  linkedin: "linkedin",
  behance: "behance",
} as const;

type SocialKey = keyof typeof SOCIAL_ICONS;
const SOCIAL_ORDER = Object.keys(SOCIAL_ICONS) as SocialKey[];

function initials(name: string): string {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("");
}

/** About page team cards from `settings.about.team`; hidden when the list is empty. */
export function AboutTeam({ title, members }: { title: string; members: AboutTeamMember[] }) {
  const { row, textAlign, writingDirection } = useRtl();
  if (members.length === 0) {
    return null;
  }

  return (
    <View style={styles.wrap}>
      <Text style={[styles.title, { textAlign, writingDirection }]}>{title}</Text>
      <View style={[styles.grid, { flexDirection: row }]}>
        {members.map((member) => {
          const links = SOCIAL_ORDER.filter((key) => Boolean(member.social?.[key]));
          return (
            <View key={member.name} style={styles.card}>
              {member.image_url ? (
                <RemoteImage uri={member.image_url} style={styles.photo} placeholderColor="#eee" />
              ) : (
                <View style={[styles.photo, styles.fallback]}>
                  <Text style={styles.initials}>{initials(member.name)}</Text>
                </View>
              )}
              <Text style={[styles.name, { textAlign, writingDirection }]}>{member.name}</Text>
              {member.role ? (
                <Text style={[styles.role, { textAlign, writingDirection }]}>{member.role}</Text>
              ) : null}
              {links.length > 0 ? (
                <View style={[styles.social, { flexDirection: row }]}>
                  {links.map((key) => (
                    <Pressable
                      key={key}
                      onPress={() => void Linking.openURL(member.social![key])}
                      accessibilityRole="link"
                      accessibilityLabel={`${member.name} ${key}`}
                      hitSlop={6}
                      style={styles.socialBtn}
                    >
                      <FontAwesome name={SOCIAL_ICONS[key]} size={16} color="#333" />
                    </Pressable>
                  ))}
                </View>
              ) : null}
            </View>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: 12, gap: 10 },
  title: { fontSize: 18, fontWeight: "800" },
  grid: { flexWrap: "wrap", justifyContent: "space-between", rowGap: 12 },
  card: {
    width: "48%",
    backgroundColor: "#fff",
    borderRadius: 12,
    overflow: "hidden",
    paddingBottom: 10,
  },
  photo: { width: "100%", aspectRatio: 280 / 360, backgroundColor: "#eee" },
  fallback: { alignItems: "center", justifyContent: "center", backgroundColor: "#1a1a1a" },
  initials: { color: "#fff", fontSize: 32, fontWeight: "800" },
  name: { fontWeight: "800", fontSize: 15, marginTop: 8, marginHorizontal: 10, color: "#111" },
  role: { color: "#666", marginHorizontal: 10, marginTop: 2 },
  social: { flexWrap: "wrap", gap: 6, marginHorizontal: 10, marginTop: 8 },
  socialBtn: {
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#f2f2f2",
  },
});
