# Login as Customer (MageAustralia)

Secure "Login as Customer" for the Maho admin. An admin with the right
permission can open a real storefront session as a customer to reproduce cart,
checkout, pricing, or account issues - with a hard audit trail and a visible
banner so the impersonation is never silent.

A security-hardened, Maho-native replacement for the legacy `Widgento_Login` /
`Spadar_Login` Magento 1 extensions.

## How it works

The module delegates the actual login to **Maho core's magic-link mechanism**,
so it never invents its own session handling:

1. On the admin **customer edit** page a **Login as Customer** button appears
   (gated by ACL + a confirm prompt).
2. Clicking it hits a CSRF-protected admin action that mints a core magic-link
   token on the customer (`generateMagicLinkToken()` + `changeResetPasswordLinkToken()`),
   writes a "requested" audit row, and redirects to core's
   `customer/account/magicLinkLogin` on the customer's own store view.
3. Core validates the token (timing-safe, expiring, one-time), establishes the
   session through the **same proven path as a normal login**, and clears the
   token.
4. A `customer_login` observer matches the login to the pending "requested" row,
   flags the session (for the banner), and records "success".
5. The storefront shows a **red impersonation banner** with an End session link.

Building on core magic-link is deliberate: it sidesteps the cross-host
session/cookie pitfalls of a hand-rolled `loginById()` handover, and inherits
core's token security and expiry.

## Security model

| Concern | Approach |
|---|---|
| Token | Core magic-link token (`rp_token`): securely generated, **timing-safe** validated (`hash_equals`) |
| One-time use | Core clears the token on login - the link cannot be replayed |
| Expiry | Core's `customer/login/magic_link_token_expiration` (default 10 min) |
| Session handling | Core's `magicLinkLogin` action - identical to normal login, no custom cookie code |
| CSRF | Admin trigger is `_setForcedFormKeyActions(['create'])` and the link carries the form key |
| Authorization | Dedicated ACL resource `admin/customer/loginascustomer` |
| Account state | Inactive customers are refused |
| Visibility | Red storefront banner ("Admin session - viewing as ...") with an End session link |
| Auditability | Every request/success/failure logged with admin, customer, IP, user agent, outcome |

## Requirements

- Maho `^26.5`, PHP `^8.3`
- **Magic Link login must be enabled**: System > Configuration > Customers >
  Login Options > *Enable Magic Link* (`customer/login/magic_link_enabled`).
  If it is off, the admin button is labelled accordingly and the action refuses
  with a clear message. The module does **not** silently toggle core config.

## Configuration

System > Configuration > Customers > **Login as Customer**

- **Enabled** - master switch
- **Show Impersonation Banner** - default yes

## Permissions

Grant the role resource **Customers > Login as Customer** to allow use, and
**Customers > Login as Customer > Login as Customer Log** for log access.

## Notes

- No core rewrites (button injected via `adminhtml_widget_container_html_before`;
  banner via a self-gating layout block; login via core magic-link).
- Reusing the magic-link (`rp_token`) field means issuing an impersonation link
  supersedes any pending customer password-reset link for that account.

## License

OSL-3.0
