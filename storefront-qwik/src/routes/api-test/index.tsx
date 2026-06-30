import { component$, useSignal, $ } from "@builder.io/qwik";
import type { DocumentHead } from "@builder.io/qwik-city";
import { API_BASE, pingApi } from "~/lib/api";

/**
 * CORS / connectivity test page.
 *
 * The fetch runs in the BROWSER (onClick$ handler) so it genuinely exercises
 * the cross-origin request from http://localhost:5173 to the production API.
 */
export default component$(() => {
  const status = useSignal<"idle" | "loading" | "success" | "error">("idle");
  const result = useSignal<string>("");

  const runPing = $(async () => {
    status.value = "loading";
    result.value = "";
    try {
      const { data } = await pingApi();
      status.value = "success";
      result.value = JSON.stringify(data, null, 2);
    } catch (err) {
      status.value = "error";
      result.value = err instanceof Error ? err.message : String(err);
    }
  });

  return (
    <div style={{ maxWidth: "640px", margin: "2rem auto", fontFamily: "system-ui, sans-serif" }}>
      <h1>Storefront API — CORS test</h1>
      <p>
        API base: <code>{API_BASE}</code>
      </p>
      <p>
        Endpoint: <code>/api/storefront/v1/ping</code>
      </p>

      <button
        onClick$={runPing}
        disabled={status.value === "loading"}
        style={{
          padding: "0.6rem 1.2rem",
          fontSize: "1rem",
          cursor: "pointer",
          borderRadius: "6px",
          border: "1px solid #333",
          background: status.value === "loading" ? "#ccc" : "#111",
          color: "#fff",
        }}
      >
        {status.value === "loading" ? "Pinging…" : "Ping API"}
      </button>

      {status.value === "success" && (
        <div style={{ marginTop: "1rem", color: "#137333" }}>
          <strong>✓ CORS OK — response received</strong>
          <pre style={{ background: "#f4f4f4", padding: "1rem", borderRadius: "6px", overflow: "auto" }}>
            {result.value}
          </pre>
        </div>
      )}

      {status.value === "error" && (
        <div style={{ marginTop: "1rem", color: "#c5221f" }}>
          <strong>✗ Request failed</strong>
          <pre style={{ background: "#fdeceb", padding: "1rem", borderRadius: "6px", overflow: "auto" }}>
            {result.value}
          </pre>
          <p style={{ fontSize: "0.9rem", color: "#555" }}>
            If this is a CORS error, ensure the production API has the new
            <code> config/cors.php </code> deployed and
            <code> CORS_ALLOWED_ORIGINS </code> includes
            <code> http://localhost:5173 </code>.
          </p>
        </div>
      )}
    </div>
  );
});

export const head: DocumentHead = {
  title: "API CORS Test",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
