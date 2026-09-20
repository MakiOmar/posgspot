/**
 * Additive Expo config plugin for the gated Geidea native RN SDK.
 *
 * No-op when `@geidea/payment-sdk-react-native` is not installed. When the
 * vendor tarball is present, adds a `flatDir` repo plus Compose/navigation
 * dependencies. Does not replace android/build.gradle (Expo SDK 57 / RN 0.86).
 */
const fs = require("fs");
const path = require("path");
const {
  AndroidConfig,
  withAppBuildGradle,
  withProjectBuildGradle,
  withStringsXml,
  withDangerousMod,
  createRunOncePlugin,
} = require("expo/config-plugins");

const PKG = "@geidea/payment-sdk-react-native";
const LOGO_SOURCE = "assets/images/geidea-merchant-logo.png";
const LOGO_ANDROID_NAME = "geidea_merchant_logo.png";

/** Geidea PGW ships `gpw_pay_now` as "Online now" — override CTA to "Pay Now". */
const PAY_BUTTON_EN = "Pay Now";
const PAY_BUTTON_AR = "ادفع الآن";

function sdkInstalled(projectRoot) {
  try {
    require.resolve(`${PKG}/package.json`, { paths: [projectRoot] });
    return true;
  } catch {
    const local = path.join(projectRoot, "node_modules", PKG, "package.json");
    return fs.existsSync(local);
  }
}

function withGeideaSdk(config) {
  config = withStringsXml(config, (mod) => {
    if (!sdkInstalled(mod.modRequest.projectRoot)) {
      return mod;
    }
    mod.modResults = AndroidConfig.Strings.setStringItem(
      [
        { $: { name: "gpw_pay_now" }, _: PAY_BUTTON_EN },
        { $: { name: "pay" }, _: PAY_BUTTON_EN },
      ],
      mod.modResults,
    );
    return mod;
  });

  config = withProjectBuildGradle(config, (mod) => {
    if (!sdkInstalled(mod.modRequest.projectRoot)) {
      return mod;
    }
    if (mod.modResults.language !== "groovy") {
      return mod;
    }
    let contents = mod.modResults.contents;
    if (!contents.includes("geideaSdkFlatDir")) {
      contents = contents.replace(
        /allprojects\s*\{\s*repositories\s*\{/,
        (match) => `${match}
    // geideaSdkFlatDir — prebuilt AARs from the Geidea RN package
    flatDir {
      dirs new File(rootProject.projectDir, "../node_modules/${PKG}/android/libs")
    }`,
      );
    }
    mod.modResults.contents = contents;
    return mod;
  });

  config = withAppBuildGradle(config, (mod) => {
    if (!sdkInstalled(mod.modRequest.projectRoot)) {
      return mod;
    }
    if (mod.modResults.language !== "groovy") {
      return mod;
    }
    let contents = mod.modResults.contents;
    if (!contents.includes("geideaSdkDeps")) {
      contents = contents.replace(
        /dependencies\s*\{/,
        (match) => `${match}
    // geideaSdkDeps — Compose / navigation required by Geidea AARs
    implementation platform("androidx.compose:compose-bom:2024.10.01")
    implementation "androidx.compose.material3:material3"
    implementation "androidx.compose.material:material-icons-extended"
    implementation "androidx.navigation:navigation-compose:2.8.3"
    implementation "androidx.navigation:navigation-runtime-ktx:2.8.3"
    implementation "androidx.activity:activity-compose:1.9.3"
    implementation "org.jetbrains.kotlinx:kotlinx-collections-immutable:0.3.8"
`,
      );
    }
    mod.modResults.contents = contents;
    return mod;
  });

  config = withDangerousMod(config, [
    "android",
    async (mod) => {
      if (!sdkInstalled(mod.modRequest.projectRoot)) {
        return mod;
      }
      const resRoot = path.join(
        mod.modRequest.platformProjectRoot,
        "app",
        "src",
        "main",
        "res",
      );

      const src = path.join(mod.modRequest.projectRoot, LOGO_SOURCE);
      if (fs.existsSync(src)) {
        const destDir = path.join(resRoot, "drawable");
        fs.mkdirSync(destDir, { recursive: true });
        fs.copyFileSync(src, path.join(destDir, LOGO_ANDROID_NAME));
      }

      // Arabic override (withStringsXml only writes values/strings.xml).
      const arDir = path.join(resRoot, "values-ar");
      fs.mkdirSync(arDir, { recursive: true });
      fs.writeFileSync(
        path.join(arDir, "strings.xml"),
        `<?xml version="1.0" encoding="UTF-8"?>
<resources>
  <string name="gpw_pay_now">${PAY_BUTTON_AR}</string>
  <string name="pay">${PAY_BUTTON_AR}</string>
</resources>
`,
        "utf8",
      );
      return mod;
    },
  ]);

  return config;
}

module.exports = createRunOncePlugin(withGeideaSdk, "with-geidea-sdk", "1.1.0");
