import { component$ } from "@builder.io/qwik";
import {
  FacebookIcon,
  InstagramIcon,
  LinkIcon,
  TiktokIcon,
  XIcon,
  YoutubeIcon,
} from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";

export interface AboutTeamMember {
  name: string;
  role: string;
  image_url: string | null;
  social: Record<string, string>;
}

const SOCIAL_ORDER = ["facebook", "instagram", "x", "youtube", "tiktok", "linkedin", "behance"] as const;

export const AboutTeam = component$<{ members: AboutTeamMember[] }>(({ members }) => {
  const { locale } = useI18n();

  if (members.length === 0) {
    return null;
  }

  return (
    <section class="about-section about-team">
      <h2 class="about-team-heading">
        {tStatic(locale, "about.our") ? (
          <span class="about-team-heading__accent">{tStatic(locale, "about.our")}</span>
        ) : null}
        {tStatic(locale, "about.team") ? <> {tStatic(locale, "about.team")}</> : null}
      </h2>
      <div class="about-team-rail">
        {members.map((member) => {
          const links = SOCIAL_ORDER.filter((key) => Boolean(member.social?.[key]));
          const initials = member.name
            .split(" ")
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0])
            .join("");

          return (
            <article key={member.name} class="about-team-card">
              <div class="about-team-photo">
                {member.image_url ? (
                  <img src={member.image_url} alt={member.name} width={280} height={360} />
                ) : (
                  <span class="about-team-photo__fallback" aria-hidden="true">
                    {initials}
                  </span>
                )}
                <div class="about-team-meta">
                  <h3>{member.name}</h3>
                  {member.role ? <p>{member.role}</p> : null}
                </div>
              </div>
              {links.length > 0 ? (
                <div class="about-team-follow">
                  <span>{tStatic(locale, "about.follow")}</span>
                  <ul>
                    {links.map((key) => (
                      <li key={key}>
                        <a
                          href={member.social[key]}
                          target="_blank"
                          rel="noopener noreferrer"
                          aria-label={key}
                        >
                          {key === "facebook" ? <FacebookIcon size={16} /> : null}
                          {key === "instagram" ? <InstagramIcon size={16} /> : null}
                          {key === "x" ? <XIcon size={16} /> : null}
                          {key === "youtube" ? <YoutubeIcon size={16} /> : null}
                          {key === "tiktok" ? <TiktokIcon size={16} /> : null}
                          {key === "linkedin" || key === "behance" ? <LinkIcon size={16} /> : null}
                        </a>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </article>
          );
        })}
      </div>
    </section>
  );
});
