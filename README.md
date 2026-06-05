# Login as Customer (MageAustralia)

Secure "Login as Customer" for the Maho admin. An admin with the right
permission can open a real storefront session as a customer to reproduce cart,
checkout, pricing, or account issues - with a hard audit trail and a visible
banner so the impersonation is never silent.

A security-hardened, Maho-native replacement for the legacy `Widgento_Login` /
`Spadar_Login` Magento 1 extensions.

## How it works

1. On the admin **customer edit** page a **Login as Customer** button appears
   (gated by ACL + a confirm prompt).
2. Clicking it hits a CSRF-protected admin action that mints a **one-time token**
   and redirects to the storefront on the customer's own store view.
3. The storefront leg **atomically consumes** the token, renews the session id,
   logs the customer in, flags the session, and shows the impersonation banner.
4. Every step is written to a permanent **audit log** (Customers > Login as
   Customer Log).

## Security model

| Concern | Approach |
|---|---|
| Token strength | 256-bit CSPRNG (`random_bytes(32)`), URL-safe encoded |
| Token at rest | Only the **SHA-256 hash** is stored; the raw token lives only in the one-time URL. A DB read cannot mint a working link. |
| Replay | **Single-use** via an atomic conditional UPDATE (affected-rows check) - safe under concurrency |
| Expiry | Short TTL (default 60s, clamped 15-3600s); expired tokens are rejected and purged |
| CSRF | Admin trigger is `_setForcedFormKeyActions(['create'])` and the link carries the form key |
| Authorization | Dedicated ACL resource `admin/customer/loginascustomer` |
| Session fixation | Session id is renewed before `loginById()` |
| Session bleed | Prior front-end sessions (cart, wishlist, etc.) and any persistent "remember me" session are cleared first |
| Visibility | Red storefront banner ("Admin session - viewing as ...") with an End session link |
| Auditability | Every request/success/failure logged with admin, customer, IP, user agent, outcome |

## Configuration

System > Configuration > Customers > **Login as Customer**

- **Enabled** - master switch
- **Token Lifetime (seconds)** - default 60
- **Show Impersonation Banner** - default yes

## Permissions

Grant the role resource **Customers > Login as Customer** to allow use, and
**Customers > Login as Customer > Login as Customer Log** for log access.

## Compatibility

- Maho `^26.5`, PHP `^8.3`
- No core rewrites (button injected via `adminhtml_widget_container_html_before`,
  banner via a self-gating layout block)

## License

OSL-3.0
