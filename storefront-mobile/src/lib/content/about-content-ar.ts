/** Arabic About page marketing copy. */

export interface AboutVisionItem {
  title: string;
  text: string;
}

export interface AboutPageContent {
  kicker: string;
  lead: string;
  whoWeAreTitle: string;
  whoWeAreBody: string;
  yearsLabel: string;
  customersLabel: string;
  visionTitle: string;
  visionItems: AboutVisionItem[];
  historyTitle: string;
  historyIntro: string;
  teamTitle: string;
}

export const ABOUT_CONTENT_AR: AboutPageContent = {
  kicker: "العب على مستواك",
  lead: "وجهتك في مصر للأجهزة والألعاب والإكسسوارات والصيانة والنصائح المتخصصة—أونلاين وفي الفروع.",
  whoWeAreTitle: "من نحن",
  whoWeAreBody:
    "متجر ألعاب بناه لاعبون للاعبين. من أحدث أجهزة بلايستيشن وإكس بوكس إلى العروض المستعملة والأكواد الرقمية والصيانة السريعة، نساعدك تستفيد أكثر من كل جلسة لعب. فريقنا يعيش الألعاب—فتحصل دائمًا على توصيات صادقة ودعم بعد الشراء.",
  yearsLabel: "سنوات من الخبرة",
  customersLabel: "عميل سعيد",
  visionTitle: "رؤيتنا",
  visionItems: [
    {
      title: "منتجات عالية الجودة",
      text: "نوفر أجهزة وإكسسوارات وألعاب أصلية من موردين موثوقين لتلعب بثقة.",
    },
    {
      title: "أسعار تنافسية",
      text: "أسعار عادلة على الجديد والمستعمل، مع عروض منتظمة في فروعنا والمتجر الإلكتروني.",
    },
    {
      title: "التركيز على العميل",
      text: "نصائح ودية في الفرع وأونلاين—سواء كنت تشتري جهازك الأول أو تطوّر إعدادك.",
    },
    {
      title: "الصدق والنزاهة",
      text: "ضمانات واضحة، وتقييم شفاف للمستعمل، وتحديثات صيانة يمكنك الوثوق بها.",
    },
  ],
  historyTitle: "نصنع التاريخ معًا",
  historyIntro: "كل افتتاح فرع وكل صيانة مكتملة جزء من قصتنا مع مجتمع الألعاب في مصر.",
  teamTitle: "فريقنا",
};
