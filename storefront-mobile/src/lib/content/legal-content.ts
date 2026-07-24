/**
 * Legal copy ported from https://gamesspoteg.com (terms, privacy, return policy).
 * Update here when policies change on the canonical site.
 */

import type { ContentLocale } from "../types";
import {
  PRIVACY_POLICY_AR,
  RETURN_POLICY_AR,
  TERMS_AND_CONDITIONS_AR,
} from "./legal-content-ar";

export interface LegalSection {
  title: string;
  paragraphs?: string[];
  list?: string[];
}

export interface LegalDocument {
  title: string;
  breadcrumbLabel: string;
  lastUpdated?: string;
  intro?: string;
  sections: LegalSection[];
  footerNote?: string;
}

export const RETURN_POLICY: LegalDocument = {
  title: "Return & Exchange Policy",
  breadcrumbLabel: "Return policy",
  intro:
    "At Games Spot, we value your trust and aim to provide the best gaming experience possible. Please read our exchange policy carefully before making a purchase.",
  sections: [
    {
      title: "No Returns — Exchange Only",
      paragraphs: [
        "We do not accept product returns. Instead, we only offer exchanges within the specified timeframes below.",
      ],
    },
    {
      title: "Exchange Timeframes",
      list: [
        "Accessories (controllers, headsets, merchandise): Exchange only within 14 days of purchase.",
        "Gaming devices & consoles: Exchange only within 30 days of purchase.",
      ],
    },
    {
      title: "Non-Exchangeable Products",
      paragraphs: [
        "Digital products, game keys, downloadable content, in-game currency, and subscriptions are not subject to return or exchange under any circumstances.",
      ],
    },
    {
      title: "Conditions for Exchange",
      list: [
        "Products must be in their original packaging and condition, without signs of use or tampering.",
        "A valid proof of purchase (receipt or invoice) is required.",
        "Exchanges are limited to items of equal or greater value (with the price difference paid by the customer).",
        "Defective items will be exchanged for the same product or model, subject to availability.",
      ],
    },
    {
      title: "Contact Us",
      paragraphs: [
        "If you have any questions about our exchange policy, please contact our support team via the Contact us page or call our hotline.",
      ],
    },
  ],
};

export const TERMS_AND_CONDITIONS: LegalDocument = {
  title: "Terms & Conditions",
  breadcrumbLabel: "Terms & Conditions",
  lastUpdated: "October 6, 2025",
  intro:
    "Welcome to Games Spot. By accessing or using our website and purchasing our products, you agree to comply with and be bound by the following Terms & Conditions. Please read them carefully before using our services.",
  sections: [
    {
      title: "1. Eligibility",
      paragraphs: [
        "By using our website, you confirm that you are at least 18 years old, or accessing under the supervision of a parent or legal guardian. Some products (such as mature-rated games) may require proof of age before purchase.",
      ],
    },
    {
      title: "2. Accounts & Security",
      list: [
        "You are responsible for maintaining the confidentiality of your account and password.",
        "You agree to accept responsibility for all activities under your account.",
        "Games Spot reserves the right to suspend or terminate accounts suspected of misuse, fraud, or violation of these terms.",
      ],
    },
    {
      title: "3. Products & Availability",
      paragraphs: [
        "We strive to ensure product descriptions, prices, and availability are accurate. However, errors may occur. In such cases, we reserve the right to cancel orders or adjust details. Availability of limited-edition items, pre-orders, or digital codes is not guaranteed until order confirmation.",
      ],
    },
    {
      title: "4. Orders & Payments",
      list: [
        "Orders are considered confirmed once payment is successfully processed.",
        "We accept various payment methods as listed during checkout.",
        "Games Spot reserves the right to refuse or cancel any order at our discretion, including suspected fraudulent transactions.",
      ],
    },
    {
      title: "5. Digital Products & Codes",
      paragraphs: [
        "All sales of digital products (such as game codes, in-game currency, and subscription keys) are final and non-refundable once delivered. You are responsible for ensuring compatibility with your system or platform before purchase.",
      ],
    },
    {
      title: "6. Pre-Orders",
      paragraphs: [
        "Pre-orders are charged at the time of purchase unless stated otherwise. Release dates are determined by publishers and may be subject to change. If a pre-order is canceled by the publisher or supplier, you will receive a full refund.",
      ],
    },
    {
      title: "7. Shipping & Delivery",
      paragraphs: [
        "Estimated delivery times are provided at checkout. While we strive to meet these timelines, Games Spot is not responsible for delays caused by couriers, customs, or unforeseen circumstances. International orders may incur duties and taxes, which are the responsibility of the customer.",
      ],
    },
    {
      title: "8. Returns & Refunds",
      paragraphs: [
        "Our Return & Exchange Policy outlines the process for eligible exchanges. Digital products, redeemed codes, and opened software are non-returnable except where required by law.",
      ],
    },
    {
      title: "9. Intellectual Property",
      paragraphs: [
        "All content on this site, including logos, graphics, text, and product descriptions, are the property of Games Spot or licensors. You may not reproduce, distribute, or exploit any content without written permission.",
      ],
    },
    {
      title: "10. User Conduct",
      list: [
        "You agree not to misuse the website, attempt unauthorized access, or interfere with its operation.",
        "You must not engage in fraudulent transactions, including reselling digital codes or exploiting promotions unfairly.",
        "Community features (if available) must be used respectfully; abusive behavior may result in account suspension.",
      ],
    },
    {
      title: "11. Limitation of Liability",
      paragraphs: [
        "Games Spot is not liable for indirect, incidental, or consequential damages arising from your use of our services or products, including but not limited to loss of data, loss of profits, or incompatibility issues.",
      ],
    },
    {
      title: "12. Privacy Policy",
      paragraphs: [
        "Your personal data is handled in accordance with our Privacy Policy. By using our services, you consent to our collection and use of information as described therein.",
      ],
    },
    {
      title: "13. Changes to Terms",
      paragraphs: [
        "We may update these Terms & Conditions periodically. Any changes will be posted on this page with the revised “Last updated” date. Continued use of our services constitutes acceptance of the updated terms.",
      ],
    },
    {
      title: "14. Contact Us",
      paragraphs: [
        "If you have any questions about these Terms & Conditions, please contact us via the Contact us page or our published hotline.",
      ],
    },
  ],
};

export const PRIVACY_POLICY: LegalDocument = {
  title: "Privacy Policy",
  breadcrumbLabel: "Privacy Policy",
  intro: "Our website address is https://gamesspoteg.com. This policy explains how we collect and use information when you shop online or contact us.",
  sections: [
    {
      title: "Who we are",
      paragraphs: [
        "Games Spot operates this online storefront and related services. Order and account data is processed to fulfill purchases and support customers.",
      ],
    },
    {
      title: "Information we collect",
      list: [
        "Account details you provide when registering (name, email, mobile, address).",
        "Order and checkout information (items, shipping address, payment method).",
        "Messages you send through our contact form.",
        "Technical data such as IP address and browser type for security and spam prevention.",
      ],
    },
    {
      title: "How we use your data",
      list: [
        "To process orders, deliveries, and customer support.",
        "To manage your account and order history.",
        "To send order confirmations and service-related communications.",
        "To respond to contact form enquiries.",
      ],
    },
    {
      title: "Cookies",
      paragraphs: [
        "We use cookies and similar technologies to keep you signed in, remember preferences, and secure the site. You can control cookies through your browser settings.",
      ],
    },
    {
      title: "Embedded content",
      paragraphs: [
        "Pages may include embedded content (for example maps). Embedded content from other websites may collect data about you according to their own privacy policies.",
      ],
    },
    {
      title: "Who we share your data with",
      paragraphs: [
        "We do not sell your personal data. We share information only as needed with payment processors, couriers, and service providers that help us operate the store, or when required by law.",
      ],
    },
    {
      title: "How long we retain your data",
      paragraphs: [
        "Order and account records are retained as long as needed for legal, accounting, and support purposes. You may request deletion of personal data where we are not required to keep it.",
      ],
    },
    {
      title: "Your rights",
      paragraphs: [
        "You may request access to, correction of, or deletion of personal data we hold about you, subject to legal and operational requirements. Contact us to exercise these rights.",
      ],
    },
    {
      title: "Where your data is sent",
      paragraphs: [
        "Visitor messages may be checked through automated spam detection services. Password reset requests may include your IP address in the reset email for security.",
      ],
    },
  ],
};

export function getReturnPolicy(locale: ContentLocale): LegalDocument {
  return locale === "ar" ? RETURN_POLICY_AR : RETURN_POLICY;
}

export function getTermsAndConditions(locale: ContentLocale): LegalDocument {
  return locale === "ar" ? TERMS_AND_CONDITIONS_AR : TERMS_AND_CONDITIONS;
}

export function getPrivacyPolicy(locale: ContentLocale): LegalDocument {
  return locale === "ar" ? PRIVACY_POLICY_AR : PRIVACY_POLICY;
}
