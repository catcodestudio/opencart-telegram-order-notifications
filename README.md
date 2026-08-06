# Telegram Order Notifications for OpenCart

Sends OpenCart shop events to Telegram — a private chat, a group, a channel or a single topic inside a forum group.

- **`main`** — OpenCart 4.x (`cc_telegram.ocmod.zip`)
- **`opencart-3.x`** — OpenCart 3.0.x (`cc_telegram-oc3.ocmod.zip`)

**Status:** v1.0.2 — both builds tested live on OpenCart 4.1.0.3 and 3.0.5.0: test message, new order, status change, low stock and the retry cron all delivered.

## Features

- **New order** — announced once, the first time an order reaches one of the statuses you tick.
- **Status change** — a separate status list, so you only hear about what matters.
- **Low stock** — checked after OpenCart subtracts stock; at most one notice per product per day.
- **Several chats at once**; forum topics supported as `-1001234567890:42`.
- **Custom templates** with tags for number, total, items, customer, phone, address, payment and shipping method, and an admin link.
- A template line whose tags all resolve to empty is dropped, so messages never show dangling labels.
- **Silent delivery** option (no notification sound).
- **"Find chat_id"** button — no third-party helper bot needed. Both buttons work before the settings are saved, using the token typed in the form.
- **Delivery log** with Telegram's HTTP status and the attempt counter; failed order messages are retried on a schedule, up to a limit you set. OpenCart 4 registers the job itself; OpenCart 3 has no scheduler, so the settings screen prints the URL for your hosting cron.
- Bot token stored encrypted.

## Install

1. *Extensions → Installer* → upload the ZIP → install, then *Extensions → Modules* → install **Telegram Order Notifications**.
2. Create a bot: send `/newbot` to [@BotFather](https://t.me/BotFather), copy the token.
3. Paste the token, press **Find chat_id** after messaging your bot, add the id, save.
4. Press **Send test message** to confirm delivery.

## Message tags

`{order_number}` `{order_id}` `{order_total}` `{order_status}` `{order_status_old}` `{order_date}`
`{items}` `{items_count}` `{customer_name}` `{customer_phone}` `{customer_email}` `{customer_note}`
`{payment_method}` `{shipping_method}` `{shipping_total}` `{billing_address}` `{shipping_address}`
`{admin_url}` `{site_name}` — plus `{product_name}` `{product_sku}` `{stock_qty}` for low-stock notices.

Telegram's HTML mode accepts only `<b>`, `<i>`, `<u>`, `<s>`, `<code>`, `<pre>` and `<a href>`.

## External service

The module talks to the Telegram Bot API (`https://api.telegram.org`) — that is how a message reaches your chat. It sends your bot token, the chat id and the rendered message. Telegram's [terms](https://telegram.org/tos) and [privacy policy](https://telegram.org/privacy) apply.

Not affiliated with Telegram or OpenCart.

## License

GPL-2.0-or-later. © 2026 CatCode — https://catcode.com.ua
