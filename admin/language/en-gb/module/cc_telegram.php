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
