<?php
// Heading
$_['heading_title'] = 'Telegram-сповіщення про замовлення';

// Text
$_['text_extension']    = 'Розширення';
$_['text_home']         = 'Головна';
$_['text_success']      = 'Налаштування збережено!';
$_['text_edit']         = 'Налаштування Telegram-сповіщень';
$_['text_connection']   = 'Підключення';
$_['text_events']       = 'Події';
$_['text_templates']    = 'Шаблони повідомлень';
$_['text_settings_tab'] = 'Налаштування';
$_['text_log_tab']      = 'Журнал';
$_['text_secret_set']   = '•••••• збережено — введіть, щоб замінити';
$_['text_token_saved']  = 'Токен збережено.';
$_['text_log']          = 'Журнал надсилань (останні 100)';
$_['text_empty']        = 'Записів немає.';
$_['text_found_chats']  = 'Знайдені чати:';
$_['text_test_title']   = 'Тестове повідомлення';
$_['text_test_store']   = 'Магазин';
$_['text_test_body']    = 'Telegram-сповіщення налаштовано.';
$_['text_test_ok']      = 'Бот @%s — повідомлення надіслано в %d чат(ів).';
$_['text_placeholders'] = 'Доступні теги:';
$_['text_lead']         = 'Миттєві повідомлення про замовлення OpenCart у Telegram-чат, групу, канал або тему форуму.';

// Entry
$_['entry_status']              = 'Статус модуля';
$_['entry_bot_token']           = 'Токен бота';
$_['entry_chat_ids']            = 'Чати для сповіщень';
$_['entry_silent']              = 'Тихі повідомлення';
$_['entry_notify_new_order']    = 'Нове замовлення';
$_['entry_new_order_statuses']  = 'Статуси нового замовлення';
$_['entry_notify_status']       = 'Зміна статусу';
$_['entry_status_statuses']     = 'Статуси для сповіщення';
$_['entry_notify_low_stock']    = 'Низький залишок';
$_['entry_low_stock_threshold'] = 'Поріг залишку';
$_['entry_retry']               = 'Повторні спроби (cron)';
$_['entry_max_attempts']        = 'Макс. спроб';
$_['entry_template_new_order']  = 'Шаблон: нове замовлення';
$_['entry_template_status']     = 'Шаблон: зміна статусу';
$_['entry_template_low_stock']  = 'Шаблон: низький залишок';

// Column
$_['column_date']   = 'Дата';
$_['column_event']  = 'Подія';
$_['column_order']  = 'Замовлення';
$_['column_chat']   = 'Чат';
$_['column_status'] = 'HTTP';
$_['column_result'] = 'Результат';

// Button
$_['button_save']     = 'Зберегти';
$_['button_test']     = 'Надіслати тестове повідомлення';
$_['button_discover'] = 'Знайти chat_id';
$_['button_log']      = 'Журнал';

// Help
$_['help_bot_token']           = 'Створіть бота у @BotFather → /newbot і вставте отриманий токен. Зберігається у зашифрованому вигляді; порожнє поле = не змінювати наявний токен.';
$_['help_chat_ids']            = 'По одному на рядок: ID чату, ID групи (відʼємний) або @username каналу. Для теми у форум-групі додайте «:ID_теми» — напр. -1001234567890:42. Рядки, що починаються з «#», ігноруються.';
$_['help_silent']              = 'Надсилати без звуку сповіщення.';
$_['help_notify_new_order']    = 'Замовлення оголошується один раз — коли вперше потрапляє в один із позначених статусів.';
$_['help_notify_status']       = 'Повідомляти, коли замовлення переходить в один із позначених статусів.';
$_['help_notify_low_stock']    = 'Перевіряється після списання залишку. Одне повідомлення на товар не частіше ніж раз на добу.';
$_['help_low_stock_threshold'] = 'Повідомляти, коли залишок товару дорівнює цьому числу або менший.';
$_['help_retry']               = 'Повторна спроба для невдалих надсилань. Потребує увімкненого OpenCart-крону.';
$_['help_templates']           = 'Telegram HTML: дозволені лише &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;s&gt;, &lt;code&gt;, &lt;pre&gt; та &lt;a href&gt;. Рядок, усі теги якого порожні, не потрапляє в повідомлення.';

// Placeholders
$_['tag_order_number']     = 'Номер замовлення';
$_['tag_order_id']         = 'ID замовлення';
$_['tag_order_total']      = 'Сума замовлення';
$_['tag_order_status']     = 'Поточний статус';
$_['tag_order_status_old'] = 'Попередній статус';
$_['tag_order_date']       = 'Дата замовлення';
$_['tag_items']            = 'Список товарів';
$_['tag_items_count']      = 'Кількість позицій';
$_['tag_customer_name']    = 'Імʼя покупця';
$_['tag_customer_phone']   = 'Телефон';
$_['tag_customer_email']   = 'Email';
$_['tag_customer_note']    = 'Коментар покупця';
$_['tag_payment_method']   = 'Спосіб оплати';
$_['tag_shipping_method']  = 'Спосіб доставки';
$_['tag_shipping_total']   = 'Вартість доставки';
$_['tag_billing_address']  = 'Адреса платника';
$_['tag_shipping_address'] = 'Адреса доставки';
$_['tag_admin_url']        = 'Посилання в адмінку';
$_['tag_site_name']        = 'Назва магазину';
$_['tag_product_name']     = 'Назва товару (низький залишок)';
$_['tag_product_sku']      = 'Артикул (низький залишок)';
$_['tag_stock_qty']        = 'Залишок (низький залишок)';
$_['tag_product_url']      = 'Посилання на товар (низький залишок)';

// Error
$_['error_permission']     = 'У вас немає прав керувати цим модулем!';
$_['error_not_configured'] = 'Спочатку вкажіть токен бота і хоча б один чат.';
$_['error_token']          = 'Помилка токена:';
$_['error_token_empty']    = 'Токен бота не задано.';
$_['error_delivery']       = 'Не доставлено:';
$_['error_no_updates']     = 'Оновлень немає. Напишіть боту повідомлення (або додайте його в групу) і спробуйте ще раз. Якщо для бота увімкнено webhook — getUpdates не працює.';
