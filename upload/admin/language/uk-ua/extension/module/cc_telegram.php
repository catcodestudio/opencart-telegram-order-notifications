<?php
// Heading
$_['heading_title']             = 'Telegram-сповіщення про замовлення';

// Text
$_['text_extension']            = 'Розширення';
$_['text_success']              = 'Налаштування збережено.';
$_['text_enabled']              = 'Увімкнено';
$_['text_disabled']             = 'Вимкнено';
$_['text_connection']           = 'Підключення';
$_['text_events']               = 'Події';
$_['text_templates']            = 'Шаблони повідомлень';
$_['text_tags']                 = 'Доступні теги';
$_['text_log']                  = 'Останні відправки';
$_['text_no_results']           = 'Подій ще немає.';
$_['text_error']                = 'Помилка';
$_['text_token_saved']          = '•••••• збережено — введіть, щоб замінити';
$_['text_token_stored']         = 'Токен збережено.';
$_['text_log_cleared']          = 'Журнал очищено.';
$_['text_test_title']           = 'Тестове повідомлення';
$_['text_test_shop']            = 'Магазин:';
$_['text_test_body']            = 'Telegram-сповіщення налаштовано.';
$_['text_test_ok']              = 'Бот @%s — повідомлення надіслано в %d чат(ів).';
$_['text_found_chats']          = 'Знайдені чати: %s';

// Tabs
$_['tab_settings']              = 'Налаштування';
$_['tab_log']                   = 'Журнал';

// Entry
$_['entry_status']              = 'Увімкнено';
$_['entry_token']               = 'Токен бота';
$_['entry_chats']               = 'Чати для сповіщень';
$_['entry_admin_url']           = 'URL адмін-панелі';
$_['entry_silent']              = 'Тихі повідомлення';
$_['entry_notify_new_order']    = 'Нове замовлення';
$_['entry_notify_status']       = 'Зміна статусу';
$_['entry_notify_low_stock']    = 'Низький залишок';
$_['entry_low_stock_qty']       = 'Поріг низького залишку';
$_['entry_template_new_order']  = 'Шаблон: нове замовлення';
$_['entry_template_status']     = 'Шаблон: зміна статусу';
$_['entry_template_low_stock']  = 'Шаблон: низький залишок';

// Help
$_['help_token']                = 'Створіть бота у @BotFather → /newbot і вставте отриманий токен. Зберігається у БД у зашифрованому вигляді.';
$_['help_chats']                = 'По одному на рядок: ID чату, ID групи (від’ємний) або @username каналу. Для теми у форум-групі додайте «:ID_теми» — напр. -1001234567890:42.';
$_['help_admin_url']            = 'Використовується для тегу {admin_url} у повідомленнях. Вкажіть повний URL вашої адмін-панелі, напр. https://example.com/admin/.';
$_['help_silent']               = 'Надсилати без звуку сповіщення.';
$_['help_tools']                = 'Кнопки працюють і до збереження — використовують токен, введений у полі вище.';
$_['help_new_order']            = 'Замовлення оголошується один раз — коли вперше потрапляє в один із позначених статусів.';
$_['help_status']               = 'Повідомляти, коли замовлення переходить у позначений статус.';
$_['help_low_stock_qty']        = 'Повідомляти, коли після списання залишок товару опускається до цього значення або нижче.';
$_['help_tags']                 = 'Рядок, усі теги якого порожні, не потрапляє в повідомлення. Дозволена розмітка Telegram: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;s&gt;, &lt;code&gt;, &lt;pre&gt;, &lt;a href&gt;.';

// Tags
$_['tag_order_number']          = 'Номер замовлення';
$_['tag_order_id']              = 'ID замовлення';
$_['tag_order_total']           = 'Сума замовлення';
$_['tag_order_status']          = 'Поточний статус';
$_['tag_order_status_old']      = 'Попередній статус';
$_['tag_order_date']            = 'Дата замовлення';
$_['tag_items']                 = 'Список товарів';
$_['tag_items_count']           = 'Кількість позицій';
$_['tag_customer_name']         = 'Ім’я покупця';
$_['tag_customer_phone']        = 'Телефон';
$_['tag_customer_email']        = 'Email';
$_['tag_customer_note']         = 'Коментар покупця';
$_['tag_payment_method']        = 'Спосіб оплати';
$_['tag_shipping_method']       = 'Спосіб доставки';
$_['tag_shipping_total']        = 'Вартість доставки';
$_['tag_billing_address']       = 'Адреса платника';
$_['tag_shipping_address']      = 'Адреса доставки';
$_['tag_admin_url']             = 'Посилання в адмінку';
$_['tag_site_name']             = 'Назва магазину';
$_['tag_product_name']          = 'Назва товару (низький залишок)';
$_['tag_product_sku']           = 'Модель/артикул (низький залишок)';
$_['tag_stock_qty']             = 'Залишок (низький залишок)';

// Columns
$_['column_date']               = 'Дата';
$_['column_order']              = 'Замовлення';
$_['column_event']              = 'Подія';
$_['column_chat']               = 'Чат';
$_['column_result']             = 'Результат';
$_['column_message']            = 'Повідомлення';

// Buttons
$_['button_save']               = 'Зберегти';
$_['button_cancel']             = 'Скасувати';
$_['button_test']               = 'Надіслати тестове повідомлення';
$_['button_find']               = 'Знайти chat_id';
$_['button_clear_log']          = 'Очистити журнал';

// Errors
$_['error_permission']          = 'У вас немає прав змінювати це розширення.';
$_['error_token']               = 'Помилка токена: %s';
$_['error_no_chats']            = 'Не вказано жодного чату.';
$_['error_not_delivered']       = 'Не доставлено: %s';
$_['error_generic']             = 'Помилка: %s';
$_['error_no_updates']          = 'Оновлень немає. Напишіть боту будь-яке повідомлення (або додайте його в групу) і спробуйте ще раз. Якщо для бота увімкнено webhook — getUpdates не працює.';
