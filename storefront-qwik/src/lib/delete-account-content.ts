/**
 * Public “how to delete your account” guide (website + mobile).
 * Soft-delete request flow — staff processes offline; not instant hard-delete.
 */

import type { StoreLocaleCode } from "./i18n/config";
import type { LegalDocument } from "./legal-content";

export const DELETE_ACCOUNT_GUIDE: LegalDocument = {
  title: "How to delete your account",
  breadcrumbLabel: "Delete account",
  intro:
    "You can request deletion of your Games Spot customer account from the website or the mobile app. Deletion is not instant: our team processes the request, and you can keep using the shop until then.",
  sections: [
    {
      title: "On the website",
      list: [
        "Sign in to your account.",
        "Open Account from the menu.",
        "Go to Login & Security.",
        "Scroll to Delete account.",
        "Tap Delete my account, then confirm with Request account deletion.",
      ],
    },
    {
      title: "In the mobile app",
      list: [
        "Sign in to the Games Spot app.",
        "Open Profile.",
        "Tap Request account deletion.",
        "Confirm in the dialog.",
      ],
    },
    {
      title: "After you request deletion",
      paragraphs: [
        "You will see a confirmation that deletion was requested. Our team will process it. If you already submitted a request, the delete button is hidden and a status message is shown instead.",
      ],
    },
    {
      title: "Need help?",
      paragraphs: [
        "If you cannot access your account or need help with deletion, contact us through the Contact us page or our published hotline.",
      ],
    },
  ],
};

export const DELETE_ACCOUNT_GUIDE_AR: LegalDocument = {
  title: "كيفية حذف حسابك",
  breadcrumbLabel: "حذف الحساب",
  intro:
    "يمكنك طلب حذف حساب عميل Games Spot من الموقع أو تطبيق الجوال. الحذف ليس فوريًا: يعالج فريقنا الطلب، ويمكنك الاستمرار في استخدام المتجر حتى ذلك الحين.",
  sections: [
    {
      title: "على الموقع",
      list: [
        "سجّل الدخول إلى حسابك.",
        "افتح الحساب من القائمة.",
        "انتقل إلى تسجيل الدخول والأمان.",
        "مرّر إلى قسم حذف الحساب.",
        "اضغط حذف حسابي، ثم أكّد عبر طلب حذف الحساب.",
      ],
    },
    {
      title: "في تطبيق الجوال",
      list: [
        "سجّل الدخول إلى تطبيق Games Spot.",
        "افتح الملف الشخصي.",
        "اضغط طلب حذف الحساب.",
        "أكّد في نافذة الحوار.",
      ],
    },
    {
      title: "بعد طلب الحذف",
      paragraphs: [
        "ستظهر رسالة تأكيد بأن طلب الحذف قد أُرسل. سيعالج فريقنا الطلب. إذا كنت قد أرسلت طلبًا مسبقًا، يُخفى زر الحذف وتظهر رسالة الحالة بدلًا منه.",
      ],
    },
    {
      title: "تحتاج مساعدة؟",
      paragraphs: [
        "إذا تعذّر الوصول إلى حسابك أو احتجت مساعدة بخصوص الحذف، تواصل معنا عبر صفحة اتصل بنا أو الخط الساخن المنشور.",
      ],
    },
  ],
};

export function getDeleteAccountGuide(locale: StoreLocaleCode): LegalDocument {
  return locale === "ar" ? DELETE_ACCOUNT_GUIDE_AR : DELETE_ACCOUNT_GUIDE;
}

/** HowTo JSON-LD steps for the public delete-account page. */
export function deleteAccountHowToJsonLd(locale: StoreLocaleCode): Record<string, unknown> {
  const doc = getDeleteAccountGuide(locale);
  const webSection = doc.sections[0];
  const steps = (webSection?.list ?? []).map((text, index) => ({
    "@type": "HowToStep",
    position: index + 1,
    name: text,
    text,
  }));

  return {
    "@context": "https://schema.org",
    "@type": "HowTo",
    name: doc.title,
    description: doc.intro ?? doc.title,
    step: steps,
  };
}
