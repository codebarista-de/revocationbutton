# Revocation Button for Shopware 6.4 / 6.5

A Shopware plugin that adds the legally mandated revocation button to the storefont.
This basically backports the native Shopware 6.6/6.7 implementation to Shopware 6.4/6.5.

## What it does

- Exposes a revocation form at `/codebarista/revocation` with the fields:
  first name, last name, email, contract number and comment.
- Sends two admin-editable mails on submission:
  - `codebarista_revocation_request.merchant` — to the shop operator.
  - `codebarista_revocation_request.customer` — confirmation to the customer.
  Both can be customised under *Settings → Email templates*.

## Adding the revocation link to the storefront

The plugin does not auto-inject the link, so you keep full control over
placement, label and visibility. To add the link the same way imprint and
privacy are exposed:

1. **Admin → Catalogues → Categories** — create a new category, e.g. "Revocation".
2. Set **Type = Link**, then pick **External link** and enter the relative URL `/codebarista/revocation`. (Internal link only allows picking existing entities, which is why we use External link with a relative path — Shopware renders it verbatim, no domain needed.)
3. **Admin → Sales Channels → (your channel) → Service menu** — add the new category to the service-menu category tree.
4. Save and clear caches. The link now appears in the footer service menu next to *Imprint* / *Privacy*.

## Configuration

Configure under *Settings → Plugins → Revocation Button → Configure*.
All fields are optional — sensible fallbacks apply when left empty.

| Setting | Default | Description |
|---|---|---|
| Merchant notification – inbox address | shop email (*Settings → Basic information*) | Inbox that receives the notification each time a customer submits the form. |
| Merchant notification – inbox display name | inbox address | Display name shown in the `To:` header of the merchant notification. |
| Outgoing mail – From: address | merchant inbox address | `From:` used for **both** outgoing mails (notification + customer confirmation). |
| Outgoing mail – From: display name | sales channel name | `From:` display name used for **both** outgoing mails. |

## Compatibility

- PHP 7.4+ / 8.x
- Shopware `~6.4.12 || ~6.5.0`

## License

MIT — see [LICENSE](LICENSE).
