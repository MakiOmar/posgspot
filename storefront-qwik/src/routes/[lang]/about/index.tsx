import { component$ } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { AboutTimeline } from "~/components/content/about-timeline";
import { JsonLd } from "~/components/seo/json-ld";
import { getAboutTimeline } from "~/lib/about-timeline";
import { useLangParam } from "~/routes/[lang]/layout";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/[lang]/layout";

const VISION_ITEMS = [
  {
    title: "Quality Products",
    text: "We stock genuine consoles, accessories, and games from trusted suppliers so you can play with confidence.",
  },
  {
    title: "Competitive Prices",
    text: "Fair pricing on new and pre-owned gear, with regular deals across our branches and online store.",
  },
  {
    title: "Customer Focus",
    text: "Friendly advice in-store and online—whether you are buying your first console or upgrading your setup.",
  },
  {
    title: "Honesty & Integrity",
    text: "Clear warranties, transparent grading on used items, and repair updates you can trust.",
  },
];

const TEAM = [
  { name: "Mohamed Salah", role: "CEO & Founder" },
  { name: "Ahmed Hassan", role: "Operations Manager" },
  { name: "Karim Ali", role: "Head of Repairs" },
  { name: "Sara Mahmoud", role: "Customer Experience" },
  { name: "Omar Nabil", role: "Retail Lead" },
];

export default component$(() => {
  const settings = useSiteSettings();
  const lang = useLangParam();
  const name = settings.value.business_name;

  return (
    <article class="content-page about-page">
      <section class="about-hero">
        <p class="about-hero-kicker">Play to your level</p>
        <h1 class="about-hero-title">{name}</h1>
        <p class="about-hero-lead">
          Egypt&apos;s home for consoles, games, accessories, repairs, and expert advice—online and in-store.
        </p>
      </section>

      <section class="about-section">
        <div class="about-split">
          <div>
            <h2 class="content-section-title">Who we are</h2>
            <p class="content-prose">
              {name} is a gaming retailer built by players, for players. From the latest PlayStation and Xbox
              hardware to pre-owned bargains, digital codes, and fast repairs, we help you get more from every
              session. Our team lives and breathes games—so you always get honest recommendations and support
              long after checkout.
            </p>
          </div>
          <div class="about-stats">
            <div class="about-stat-card">
              <strong>5+</strong>
              <span>years of experience</span>
            </div>
            <div class="about-stat-card">
              <strong>2K+</strong>
              <span>happy customers</span>
            </div>
          </div>
        </div>
      </section>

      <section class="about-section about-vision">
        <h2 class="content-section-title">Our vision</h2>
        <div class="about-vision-grid">
          {VISION_ITEMS.map((item) => (
            <div key={item.title} class="about-vision-card">
              <h3>{item.title}</h3>
              <p>{item.text}</p>
            </div>
          ))}
        </div>
      </section>

      <section class="about-section about-history">
        <h2 class="content-section-title content-section-title--center">Making history together</h2>
        <p class="content-prose about-intro about-intro--center">
          Every branch opening and every repair completed is part of our story with the gaming community in Egypt.
        </p>
        <AboutTimeline items={getAboutTimeline(lang.value)} />
      </section>

      <section class="about-section">
        <h2 class="content-section-title">Our team</h2>
        <div class="about-team-grid">
          {TEAM.map((member) => (
            <div key={member.name} class="about-team-card">
              <div class="about-team-avatar" aria-hidden="true">
                {member.name
                  .split(" ")
                  .map((part) => part[0])
                  .join("")}
              </div>
              <h3>{member.name}</h3>
              <p>{member.role}</p>
            </div>
          ))}
        </div>
      </section>

      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "Organization",
          name,
          url: "/",
        }}
      />
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const title = `About us — ${settings.business_name}`;
  const description = `Learn about ${settings.business_name}—our story, team, and commitment to gamers in Egypt.`;

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { name: "twitter:card", content: "summary" },
      ],
    },
    settings,
  );
};
