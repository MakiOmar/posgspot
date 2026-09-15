/**
 * Static Egypt governorates — used when GET /geo/states/EG fails (e.g. POS DB blip).
 * Keep in sync with resources/data/geo/countries-states.json → states.EG.
 */
export const EGYPT_GEO_STATES: Array<{ code: string; name: string }> = [
  { code: "ALX", name: "Alexandria" },
  { code: "ASN", name: "Aswan" },
  { code: "AST", name: "Asyut" },
  { code: "BNS", name: "Beni Suef" },
  { code: "C", name: "Cairo" },
  { code: "DK", name: "Dakahlia" },
  { code: "DT", name: "Damietta" },
  { code: "FYM", name: "Faiyum" },
  { code: "GH", name: "Gharbia" },
  { code: "GZ", name: "Giza" },
  { code: "IS", name: "Ismailia" },
  { code: "KFS", name: "Kafr el-Sheikh" },
  { code: "LX", name: "Luxor" },
  { code: "MT", name: "Matrouh" },
  { code: "MN", name: "Minya" },
  { code: "MNF", name: "Monufia" },
  { code: "WAD", name: "New Valley" },
  { code: "SIN", name: "North Sinai" },
  { code: "PTS", name: "Port Said" },
  { code: "KB", name: "Qalyubia" },
  { code: "KN", name: "Qena" },
  { code: "BH", name: "Red Sea" },
  { code: "SHR", name: "Sharqia" },
  { code: "SHG", name: "Sohag" },
  { code: "JS", name: "South Sinai" },
  { code: "SUZ", name: "Suez" },
];
