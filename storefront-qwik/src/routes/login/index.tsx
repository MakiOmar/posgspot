import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, loginCustomer } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";

export default component$(() => {
  const auth = useAuth();
  const nav = useNavigate();
  const loc = useLocation();
  const form = useStore({ login: "", password: "" });
  const submitting = useSignal(false);
  const error = useSignal<string | null>(null);

  const nextUrl = loc.url.searchParams.get("next") || "/account";

  // If already signed in, skip the login page.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (auth.ready && auth.token) {
      nav(nextUrl);
    }
  });

  const submit$ = $(async () => {
    submitting.value = true;
    error.value = null;
    try {
      const { data } = await loginCustomer({ login: form.login, password: form.password });
      auth.token = data.token;
      auth.contact = data.contact;
      await nav(nextUrl);
    } catch (e) {
      error.value =
        e instanceof ApiError && e.status === 422
          ? "Invalid email/mobile or password."
          : "Could not sign in. Please try again.";
    } finally {
      submitting.value = false;
    }
  });

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">Sign in</h1>
        {error.value ? <p class="alert alert-error">{error.value}</p> : null}

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-field form-field--full">
            <label for="login">Email or mobile</label>
            <input
              id="login"
              type="text"
              autoComplete="username"
              value={form.login}
              onInput$={(_, el) => (form.login = el.value)}
              required
            />
          </div>
          <div class="form-field form-field--full">
            <label for="password">Password</label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              value={form.password}
              onInput$={(_, el) => (form.password = el.value)}
              required
            />
          </div>
          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value ? "Signing in…" : "Sign in"}
          </button>
        </form>

        <div class="auth-links">
          <Link href="/forgot-password" class="link-accent">
            Forgot password?
          </Link>
          <span>
            New here? <Link href="/register" class="link-accent">Create an account</Link>
          </span>
        </div>
      </div>
    </section>
  );
});

export const head: DocumentHead = {
  title: "Sign in",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
