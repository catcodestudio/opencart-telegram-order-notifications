# Telegram Order Notifications for OpenCart 4 — changelog

## 1.1.2 — 2026-10-23
- Bot token is stored with a cipher that needs only core PHP. A token saved
  where PHP had sodium could not be read on a host whose web PHP lacks it, and
  every message failed with "Not Found". A token saved by an older version that
  cannot be read now shows as empty: enter it again in the settings.

## 1.1.1 — 2026-09-23
- Status change message: the previous status is taken from the order itself
  right before the change (a new `addHistory/before` hook), not from the order
  history. Other extensions (CRM / delivery connectors) append their own history
  rows with the same status in the same request, and the message read
  "Shipped → Shipped". The hook is registered on install and, for an existing
  install, on the next visit to the module settings.
- A history row that repeats the current status (a payment callback, a comment)
  no longer produces a "status changed" message.

## 1.1.0
- Pro: Viber / SMS to customers about their order status (TurboSMS, Alpha SMS, SMS-Fly).
