/** Static FAQ content for the public storefront. */

export interface FaqEntry {
  question: string;
  answer: string;
}

export const FAQ_ENTRIES: FaqEntry[] = [
  {
    question: "What is the warranty for consoles and accessories?",
    answer:
      "Warranty coverage depends on the product and whether it is new or pre-owned. New consoles and official accessories include manufacturer warranty where applicable. Pre-owned items include a limited store warranty—ask our team at checkout for the exact period on your item.",
  },
  {
    question: "How do I track my repair?",
    answer:
      "Use Track my repairs in the main menu and enter your job sheet number, invoice number, or mobile number. You will see the current repair status and updates from our service team.",
  },
  {
    question: "How do I track my console service online?",
    answer:
      "Open Track my console from the menu. Sign in with your account details on the device tracking portal to follow console servicing progress.",
  },
  {
    question: "Do you offer trade-ins or buy used consoles?",
    answer:
      "Yes. Visit any Games Spot branch with your console and accessories. Our team will inspect the device and offer a trade-in or buy-back value toward your next purchase.",
  },
  {
    question: "Which payment methods do you accept online?",
    answer:
      "Online checkout supports card payments and cash on delivery where enabled for your area. In-store purchases also accept cash and major cards at the counter.",
  },
  {
    question: "How long does delivery take?",
    answer:
      "Orders inside Greater Cairo are usually delivered within 1–3 business days. Other governorates may take longer depending on courier coverage. You will receive updates once your order ships.",
  },
  {
    question: "Can I pick up my order from a branch?",
    answer:
      "Yes, when pickup is enabled for your branch at checkout. Choose the store location and we will notify you when the order is ready for collection.",
  },
  {
    question: "What should I do if a game or accessory is defective?",
    answer:
      "Contact us within the warranty window with your invoice or order number. Bring the item to the branch where you purchased it, or reach out via the contact form and we will guide you through exchange or repair.",
  },
  {
    question: "Are digital games and gift cards refundable?",
    answer:
      "Digital codes and gift cards are generally non-refundable once the code is revealed or delivered, unless required by law or the publisher allows a reversal.",
  },
  {
    question: "How can I contact customer support?",
    answer:
      "Call our hotline, use the contact form on the Contact us page, or visit any Games Spot branch listed on the map. Our team is happy to help with orders, repairs, and product questions.",
  },
];
