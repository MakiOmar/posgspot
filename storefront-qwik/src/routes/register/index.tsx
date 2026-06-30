import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, registerCustomer } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { toastError } from "~/lib/notify";

export default component$(() => {
  const auth = useAuth();
  const nav = useNavigate();
  const form = useStore({
    first_name: "",
    last_name: "",
    email: "",
    mobile: "",
    password: "",
    password_confirmation: "",
  });
  const submitting = useSignal(false);
  const succeeded = useSignal(false);

  // Already signed in: go to account.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (auth.ready && auth.token) {
      nav("/account");
    }
  });

  const submit$ = $(async () => {
    if (form.password !== form.password_confirmation) {
      await toastError("Passwords do not match.");
      return;
    }

    submitting.value = true;
    try {
      const { data } = await registerCustomer({
        first_name: form.first_name,
        last_name: form.last_name,
        email: form.email,
        mobile: form.mobile,
        password: form.password,
        password_confirmation: form.password_confirmation,
      });
      // Swap the form for a success/redirecting message before navigating.
      succeeded.value = true;
      auth.token = data.token;
      auth.contact = data.contact;
      await nav("/account");
    } catch (e) {
      if (e instanceof ApiError && e.errors) {
        const first = Object.values(e.errors)[0]?.[0];
        await toastError(first || "Could not create your account.");
      } else {
        await toastError("Could not create your account. Please try again.");
      }
    } finally {
      submitting.value = false;
    }
  });

  // After a successful registration, hide the form and show a confirmation
  // while the client redirects to the account area.
  if (succeeded.value) {
    return (
      <section class="auth-page container">
        <div class="auth-card">
          <h1 class="page-title">Account created</h1>
          <p class="alert alert-success">
            Welcome aboard! Your account is ready — redirecting you to your account…
          </p>
          <div class="auth-links">
            <Link href="/account" class="link-accent">
              Go to my account now
            </Link>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">Create account</h1>

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-grid">
            <div class="form-field">
              <label for="first_name">First name</label>
              <input id="first_name" type="text" value={form.first_name} onInput$={(_, el) => (form.first_name = el.value)} required />
            </div>
            <div class="form-field">
              <label for="last_name">Last name</label>
              <input id="last_name" type="text" value={form.last_name} onInput$={(_, el) => (form.last_name = el.value)} />
            </div>
            <div class="form-field">
              <label for="email">Email</label>
              <input id="email" type="email" autoComplete="email" value={form.email} onInput$={(_, el) => (form.email = el.value)} required />
            </div>
            <div class="form-field">
              <label for="mobile">Mobile</label>
              <input id="mobile" type="tel" autoComplete="tel" value={form.mobile} onInput$={(_, el) => (form.mobile = el.value)} required />
            </div>
            <div class="form-field">
              <label for="password">Password</label>
              <input id="password" type="password" autoComplete="new-password" value={form.password} onInput$={(_, el) => (form.password = el.value)} required />
            </div>
            <div class="form-field">
              <label for="password_confirmation">Confirm password</label>
              <input id="password_confirmation" type="password" autoComplete="new-password" value={form.password_confirmation} onInput$={(_, el) => (form.password_confirmation = el.value)} required />
            </div>
          </div>
          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value ? "Creating account…" : "Create account"}
          </button>
        </form>

        <div class="auth-links">
          <span>
            Already have an account? <Link href="/login" class="link-accent">Sign in</Link>
          </span>
        </div>
      </div>
    </section>
  );
});

export const head: DocumentHead = {
  title: "Create account",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
