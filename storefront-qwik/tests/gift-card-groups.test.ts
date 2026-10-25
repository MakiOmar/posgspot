import assert from "node:assert/strict";
import { test } from "node:test";
import { filterGiftCards, giftCardBrowseQuery, groupGiftCards, hasClassifications } from "../src/lib/gift-card-groups.ts";
import type { DigitalCardCategory } from "../src/lib/types.ts";

let nextId = 1;

function card(brand: string, country: string): DigitalCardCategory {
  return {
    id: nextId++,
    name: `${brand} ${country}`,
    price: 100,
    classification: {
      brand,
      brand_label: brand === "other" ? "Other" : brand[0].toUpperCase() + brand.slice(1),
      logo_url: null,
      color: "#000000",
      country,
      country_label: country.toUpperCase(),
      flag_url: null,
    },
  };
}

const cards = [
  card("amazon", "sa"),
  card("amazon", "sa"),
  card("amazon", "other"),
  card("amazon", "global"),
  card("amazon", "gb"),
  card("other", "us"),
  card("steam", "eu"),
  card("steam", "eu"),
];

test("groups brands by count with Other last", () => {
  const groups = groupGiftCards(cards);
  assert.deepEqual(groups.map((group) => [group.brand, group.count]), [["amazon", 5], ["steam", 2], ["other", 1]]);
});

test("orders countries by count, then Global, then Other", () => {
  const amazon = groupGiftCards(cards)[0];
  assert.deepEqual(amazon.countries.map((country) => [country.key, country.count]), [
    ["sa", 2],
    ["gb", 1],
    ["global", 1],
    ["other", 1],
  ]);
});

test("filters by brand and country; missing classification counts as Other", () => {
  assert.equal(filterGiftCards(cards, "amazon", "sa").length, 2);
  assert.equal(filterGiftCards(cards, "amazon", null).length, 5);
  const legacy: DigitalCardCategory = { id: 99, name: "Legacy", price: 1 };
  assert.equal(filterGiftCards([legacy], "other", "other").length, 1);
  assert.equal(hasClassifications([legacy]), false);
  assert.equal(hasClassifications(cards), true);
});

test("builds browse queries", () => {
  assert.equal(giftCardBrowseQuery("amazon", "sa"), "?brand=amazon&country=sa");
  assert.equal(giftCardBrowseQuery("amazon"), "?brand=amazon");
});
