# Mobile port backlog

Build specs for storefront behavior shipped on web (`storefront-qwik`) or `/api/storefront/v1` that is not in `storefront-mobile` yet.

**Architecture:** [`MOBILE.md`](./MOBILE.md) · **Shipped mobile work:** [`MOBILE_PROGRESS.md`](./MOBILE_PROGRESS.md)

Add an entry only when the change is customer-facing and mobile was not updated in the same task. Each entry must be enough to implement the mobile change without re-reading the web feature. When the port ships, set **Status** to `done` and list the mobile files.

## Entry shape

```markdown
## YYYY-MM-DD — Title
- Status: pending
- Behavior: what the customer does and sees, including guest vs signed-in, feature flag, empty, loading, and error
- Web: paths and symbol names that implement it
- API: method, path, request fields, and response fields the UI reads (omit unchanged shared calls)
- Client state: storage keys, cart or line meta, headers
- Copy: new i18n keys with en and ar strings
- Mobile: route and component to add or edit
- Build: ordered file-level steps to implement on mobile
- Done when: checks that prove parity
```

## Pending

_None yet._
