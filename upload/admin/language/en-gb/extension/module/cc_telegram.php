<?php
// Heading
$_['heading_title']             = 'Telegram Order Notifications';

// Text
$_['text_home']                 = 'Home';
$_['text_extension']            = 'Extensions';
$_['text_success']              = 'Settings saved.';
$_['text_enabled']              = 'Enabled';
$_['text_disabled']             = 'Disabled';
$_['text_connection']           = 'Connection';
$_['text_events']               = 'Events';
$_['text_templates']            = 'Message templates';
$_['text_tags']                 = 'Available tags';
$_['text_log']                  = 'Recent deliveries';
$_['text_no_results']           = 'No events yet.';
$_['text_error']                = 'Error';
$_['text_token_saved']          = '•••••• stored — type to replace';
$_['text_token_stored']         = 'Token stored.';
$_['text_log_cleared']          = 'Log cleared.';
$_['text_test_title']           = 'Test message';
$_['text_test_shop']            = 'Store:';
$_['text_test_body']            = 'Telegram notifications are configured.';
$_['text_test_ok']              = 'Bot @%s — message delivered to %d chat(s).';
$_['text_found_chats']          = 'Chats found: %s';

// Tabs
$_['tab_settings']              = 'Settings';
$_['tab_log']                   = 'Log';

// Entry
$_['entry_status']              = 'Status';
$_['entry_token']               = 'Bot token';
$_['entry_chats']               = 'Notification chats';
$_['entry_admin_url']           = 'Admin panel URL';
$_['entry_silent']              = 'Silent messages';
$_['entry_notify_new_order']    = 'New order';
$_['entry_notify_status']       = 'Status change';
$_['entry_notify_low_stock']    = 'Low stock';
$_['entry_low_stock_qty']       = 'Low stock threshold';
$_['entry_retry']               = 'Retry failed deliveries';
$_['entry_max_attempts']        = 'Maximum attempts';
$_['entry_template_new_order']  = 'Template: new order';
$_['entry_template_status']     = 'Template: status change';
$_['entry_template_low_stock']  = 'Template: low stock';

// Help
$_['help_token']                = 'Create a bot in @BotFather → /newbot and paste the token here. It is stored encrypted.';
$_['help_chats']                = 'One per line: a chat id, a group id (negative) or a channel @username. For a forum topic append ":topic_id" — e.g. -1001234567890:42.';
$_['help_admin_url']            = 'Used by the {admin_url} tag. Enter the full URL of your admin panel, e.g. https://example.com/admin/.';
$_['help_silent']               = 'Deliver without a notification sound.';
$_['help_tools']                = 'These buttons also work before saving — they use the token typed in the field above.';
$_['help_new_order']            = 'An order is announced once, the first time it reaches one of the ticked statuses.';
$_['help_status']               = 'Notify when an order moves into a ticked status.';
$_['help_low_stock_qty']        = 'Notify when, after stock subtraction, a product quantity drops to this value or below.';
$_['help_retry']                = 'If Telegram did not answer, the order message is sent again on a schedule. The text is re-rendered from the live order.';
$_['help_max_attempts']         = 'How many times to try delivering one message, the first attempt included.';
$_['help_cron']                 = 'OpenCart 3 has no built-in scheduler, so add this URL to your hosting cron — once an hour:';
$_['help_tags']                 = 'A line whose tags all resolve to empty is dropped from the message. Telegram markup allowed: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;s&gt;, &lt;code&gt;, &lt;pre&gt;, &lt;a href&gt;.';

// Tags
$_['tag_order_number']          = 'Order number';
$_['tag_order_id']              = 'Order ID';
$_['tag_order_total']           = 'Order total';
$_['tag_order_status']          = 'Current status';
$_['tag_order_status_old']      = 'Previous status';
$_['tag_order_date']            = 'Order date';
$_['tag_items']                 = 'Item list';
$_['tag_items_count']           = 'Number of line items';
$_['tag_customer_name']         = 'Customer name';
$_['tag_customer_phone']        = 'Phone';
$_['tag_customer_email']        = 'Email';
$_['tag_customer_note']         = 'Customer comment';
$_['tag_payment_method']        = 'Payment method';
$_['tag_shipping_method']       = 'Shipping method';
$_['tag_shipping_total']        = 'Shipping cost';
$_['tag_billing_address']       = 'Billing address';
$_['tag_shipping_address']      = 'Shipping address';
$_['tag_admin_url']             = 'Link to the admin panel';
$_['tag_site_name']             = 'Store name';
$_['tag_product_name']          = 'Product name (low stock)';
$_['tag_product_sku']           = 'Model/SKU (low stock)';
$_['tag_stock_qty']             = 'Remaining quantity (low stock)';

// Columns
$_['column_date']               = 'Date';
$_['column_order']              = 'Order';
$_['column_event']              = 'Event';
$_['column_chat']               = 'Chat';
$_['column_result']             = 'Result';
$_['column_attempts']           = 'Attempts';
$_['column_message']            = 'Message';

// Buttons
$_['button_save']               = 'Save';
$_['button_cancel']             = 'Cancel';
$_['button_test']               = 'Send test message';
$_['button_find']               = 'Find chat_id';
$_['button_clear_log']          = 'Clear log';

// Errors
$_['error_permission']          = 'You do not have permission to modify this extension.';
$_['error_token']               = 'Token error: %s';
$_['error_no_chats']            = 'No chats specified.';
$_['error_not_delivered']       = 'Not delivered: %s';
$_['error_generic']             = 'Error: %s';
$_['error_no_updates']          = 'No updates. Send the bot any message (or add it to a group) and try again. If the bot has a webhook enabled, getUpdates will not work.';
