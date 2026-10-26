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
$_['text_secret_set']          = '•••••• saved — type a new one to replace';
$_['text_placeholders']        = 'Available placeholders:';
$_['error_sms_phone']          = 'The number was not recognised. Enter it with the country code, e.g. +380XXXXXXXXX.';
