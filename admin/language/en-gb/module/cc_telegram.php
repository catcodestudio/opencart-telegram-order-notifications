<?php
// Heading
$_['heading_title'] = 'Telegram Order Notifications';

// Text
$_['text_extension']    = 'Extensions';
$_['text_home']         = 'Home';
$_['text_success']      = 'Settings saved!';
$_['text_edit']         = 'Telegram notification settings';
$_['text_connection']   = 'Connection';
$_['text_events']       = 'Events';
$_['text_templates']    = 'Message templates';
$_['text_settings_tab'] = 'Settings';
$_['text_log_tab']      = 'Log';
$_['text_secret_set']   = '•••••• saved — type a new one to replace';
$_['text_token_saved']  = 'Token saved.';
$_['text_log']          = 'Delivery log (last 100)';
$_['text_empty']        = 'No entries yet.';
$_['text_found_chats']  = 'Chats found:';
$_['text_test_title']   = 'Test message';
$_['text_test_store']   = 'Store';
$_['text_test_body']    = 'Telegram notifications are configured.';
$_['text_test_ok']      = 'Bot @%s — message delivered to %d chat(s).';
$_['text_placeholders'] = 'Available placeholders:';
$_['text_lead']         = 'Instant OpenCart order notifications in a Telegram chat, group, channel or forum topic.';

// Entry
$_['entry_status']              = 'Module status';
$_['entry_bot_token']           = 'Bot token';
$_['entry_chat_ids']            = 'Notification chats';
$_['entry_silent']              = 'Silent messages';
$_['entry_notify_new_order']    = 'New order';
$_['entry_new_order_statuses']  = 'New order statuses';
$_['entry_notify_status']       = 'Status change';
$_['entry_status_statuses']     = 'Statuses to announce';
$_['entry_notify_low_stock']    = 'Low stock';
$_['entry_low_stock_threshold'] = 'Stock threshold';
$_['entry_retry']               = 'Retry failed sends (cron)';
$_['entry_max_attempts']        = 'Max attempts';
$_['entry_template_new_order']  = 'Template: new order';
$_['entry_template_status']     = 'Template: status change';
$_['entry_template_low_stock']  = 'Template: low stock';

// Column
$_['column_date']   = 'Date';
$_['column_event']  = 'Event';
$_['column_order']  = 'Order';
$_['column_chat']   = 'Chat';
$_['column_status'] = 'HTTP';
$_['column_result'] = 'Result';

// Button
$_['button_save']     = 'Save';
$_['button_test']     = 'Send test message';
$_['button_discover'] = 'Find chat_id';
$_['button_log']      = 'Log';

// Help
$_['help_bot_token']           = 'Create a bot via @BotFather → /newbot and paste the token. Stored encrypted; leave empty to keep the current token.';
$_['help_chat_ids']            = 'One per line: a chat id, a group id (negative) or a channel @username. For a forum topic append ":topic_id" — e.g. -1001234567890:42. Lines starting with "#" are ignored.';
$_['help_silent']              = 'Deliver without a notification sound.';
$_['help_notify_new_order']    = 'An order is announced once — the first time it reaches one of the selected statuses.';
$_['help_notify_status']       = 'Announce when an order moves into one of the selected statuses.';
$_['help_notify_low_stock']    = 'Checked after OpenCart subtracts stock. At most one message per product per day.';
$_['help_low_stock_threshold'] = 'Notify when a product quantity drops to this number or below.';
$_['help_retry']               = 'One more attempt for failed deliveries. Requires the OpenCart cron to be running.';
$_['help_templates']           = 'Telegram HTML: only &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;s&gt;, &lt;code&gt;, &lt;pre&gt; and &lt;a href&gt; are allowed. A line whose placeholders all resolve to empty is dropped.';

// Placeholders
$_['tag_order_number']     = 'Order number';
$_['tag_order_id']         = 'Order ID';
$_['tag_order_total']      = 'Order total';
$_['tag_order_status']     = 'Current status';
$_['tag_order_status_old'] = 'Previous status';
$_['tag_order_date']       = 'Order date';
$_['tag_items']            = 'Item list';
$_['tag_items_count']      = 'Item count';
$_['tag_customer_name']    = 'Customer name';
$_['tag_customer_phone']   = 'Phone';
$_['tag_customer_email']   = 'Email';
$_['tag_customer_note']    = 'Customer comment';
$_['tag_payment_method']   = 'Payment method';
$_['tag_shipping_method']  = 'Shipping method';
$_['tag_shipping_total']   = 'Shipping cost';
$_['tag_billing_address']  = 'Billing address';
$_['tag_shipping_address'] = 'Shipping address';
$_['tag_admin_url']        = 'Admin link';
$_['tag_site_name']        = 'Store name';
$_['tag_product_name']     = 'Product name (low stock)';
$_['tag_product_sku']      = 'SKU (low stock)';
$_['tag_stock_qty']        = 'Quantity left (low stock)';
$_['tag_product_url']      = 'Product link (low stock)';

// Error
$_['error_permission']     = 'You do not have permission to modify this module!';
$_['error_not_configured'] = 'Set a bot token and at least one chat first.';
$_['error_token']          = 'Token error:';
$_['error_token_empty']    = 'Bot token is empty.';
$_['error_delivery']       = 'Not delivered:';
$_['error_no_updates']     = 'No updates. Send the bot a message (or add it to a group) and try again. getUpdates does not work while a webhook is set for the bot.';

// Pro: Viber / SMS to customers
$_['text_sms_tab']             = 'Viber / SMS to customers';
$_['text_sms_lead']            = 'Telegram alerts for you are free. Viber and SMS messages to your customers about their order status are a Pro feature: 7 days free, then a one-time lifetime licence from catcode.com.ua.';
$_['text_license']             = 'Pro licence';
$_['text_pro_active']          = 'Pro active';
$_['text_pro_inactive']        = 'Pro inactive';
$_['text_license_owned']       = 'licence purchased — yours for good';
$_['text_trial_left']          = 'trial, days left: %d';
$_['text_license_invalid']     = 'the key is invalid or the trial has ended';
$_['text_buy_license']         = 'Buy a licence on catcode.com.ua';
$_['text_license_activated']   = 'Licence activated.';
$_['text_license_deactivated'] = 'Key released.';
$_['text_trial_started']       = 'Your 7-day trial has started.';
$_['text_sms_gateway']         = 'Viber / SMS gateway';
$_['text_sms_locked']          = 'You can set everything up now; sending starts as soon as a trial or a licence is active.';
$_['text_sms_rules']           = 'Texts by order status';
$_['text_sms_check']           = 'Check';
$_['text_sms_saved']           = 'Viber / SMS settings saved.';
$_['text_sms_balance']         = '%s: key is valid, balance %s.';
$_['text_sms_test_body']       = '%s: test message. Customer notifications are set up.';
$_['text_sms_test_ok']         = 'Sent to %s, ID %s.';
$_['text_channel_hybrid']      = 'Viber, SMS if not delivered';
$_['text_channel_viber']       = 'Viber only';
$_['text_channel_sms']         = 'SMS only';
$_['text_send']                = 'Send';
$_['text_key_saved']           = 'Key saved.';
$_['entry_license_key']        = 'Licence key';
$_['entry_trial']              = 'Try Pro';
$_['entry_sms_enabled']        = 'Customer messages';
$_['entry_sms_gateway']        = 'Service';
$_['entry_sms_key']            = 'Token / API key';
$_['entry_sms_channel']        = 'Channel';
$_['entry_sms_senders']        = 'SMS: sender name per country';
$_['entry_viber_senders']      = 'Viber: sender per country';
$_['entry_sms_country']        = 'Default country';
$_['button_license_activate']   = 'Activate';
$_['button_license_deactivate'] = 'Release key';
$_['button_trial']              = 'Start 7 days free';
$_['button_sms_balance']        = 'Check key and balance';
$_['button_sms_test']           = 'Send test';
$_['help_license_deactivate']  = 'Release the key to move the licence to another domain.';
$_['help_trial']               = 'A 7-day key is sent to this e-mail and activated right away. No automatic charges.';
$_['help_sms_gateway']         = 'TurboSMS — token from the API section; Alpha SMS — API key in your account; SMS-Fly — key from "API settings".';
$_['help_sms_key']             = 'Stored encrypted; leave empty to keep the saved one.';
$_['help_sms_senders']         = 'One per line: country code=name, exactly as registered with the service. "*" covers every other country. The country comes from the number and the order.';
$_['help_sms_country']         = 'Used for numbers without a country code when the order has no country. The number always goes to the service in E.164.';
$_['help_sms_rules']           = 'Each status is sent to a customer once. Plain text, no markup; a line whose tags are all empty is skipped.';
$_['help_sms_tags']            = '{comment} is the comment added together with the status; {tracking_number} is the first 11–14 digit number in that comment (the waybill).';
$_['help_sms_balance']         = 'Sends nothing and costs nothing.';
$_['help_sms_test']            = 'A real message at your service rates.';
$_['error_sms_gateway']        = 'The service refused:';
$_['error_sms_pro']            = 'The test message needs an active Pro.';
$_['error_license_email']      = 'Enter a valid e-mail address — the key will be sent there.';
$_['error_license_format']     = 'Enter a licence key.';
$_['error_license_invalid']    = 'Key not found. Check that you copied it in full.';
$_['error_license_expired']    = 'The 7-day trial is over. Buy a licence on catcode.com.ua.';
$_['error_license_limit']      = 'This key is already in use on another store. Release it there first.';
$_['error_license_product']    = 'This key belongs to another CatCode module.';
$_['error_trial_used']         = 'A trial has already been issued for this store.';
$_['error_license_network']    = 'Could not reach the licence server. Try again in a few minutes.';
$_['error_license_generic']    = 'This key does not fit.';
