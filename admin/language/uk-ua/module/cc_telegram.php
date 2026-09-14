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

// Pro: Viber / SMS to customers
$_['text_sms_tab']             = 'Viber / SMS покупцям';
$_['text_sms_lead']            = 'Telegram-сповіщення для вас — безкоштовні. Повідомлення покупцям у Viber і SMS про статус замовлення — Pro-функція: 7 днів безкоштовно, далі одноразова ліцензія назавжди на catcode.com.ua.';
$_['text_license']             = 'Pro-ліцензія';
$_['text_pro_active']          = 'Pro активний';
$_['text_pro_inactive']        = 'Pro не активний';
$_['text_license_owned']       = 'ліцензію придбано — назавжди';
$_['text_trial_left']          = 'пробний період, залишилось днів: %d';
$_['text_license_invalid']     = 'ключ недійсний або пробний період завершився';
$_['text_buy_license']         = 'Купити ліцензію на catcode.com.ua';
$_['text_license_activated']   = 'Ліцензію активовано.';
$_['text_license_deactivated'] = 'Ключ звільнено.';
$_['text_trial_started']       = 'Пробний період на 7 днів почався.';
$_['text_sms_gateway']         = 'Шлюз Viber / SMS';
$_['text_sms_locked']          = 'Налаштувати можна вже зараз, а надсилання почнеться, щойно буде активний пробний період або ліцензія.';
$_['text_sms_rules']           = 'Тексти за статусами';
$_['text_sms_check']           = 'Перевірка';
$_['text_sms_saved']           = 'Налаштування Viber / SMS збережено.';
$_['text_sms_balance']         = '%s: ключ дійсний, баланс %s.';
$_['text_sms_test_body']       = '%s: тестове повідомлення. Сповіщення покупцям налаштовано.';
$_['text_sms_test_ok']         = 'Надіслано на %s, ID %s.';
$_['text_channel_hybrid']      = 'Viber, якщо не доставлено — SMS';
$_['text_channel_viber']       = 'Лише Viber';
$_['text_channel_sms']         = 'Лише SMS';
$_['text_send']                = 'Надсилати';
$_['text_key_saved']           = 'Ключ збережено.';
$_['entry_license_key']        = 'Ключ ліцензії';
$_['entry_trial']              = 'Спробувати Pro';
$_['entry_sms_enabled']        = 'Повідомлення покупцям';
$_['entry_sms_gateway']        = 'Сервіс';
$_['entry_sms_key']            = 'Токен / API-ключ';
$_['entry_sms_channel']        = 'Канал';
$_['entry_sms_senders']        = 'SMS: альфа-імʼя за країною';
$_['entry_viber_senders']      = 'Viber: відправник за країною';
$_['entry_sms_country']        = 'Країна за замовчуванням';
$_['button_license_activate']   = 'Активувати';
$_['button_license_deactivate'] = 'Звільнити ключ';
$_['button_trial']              = 'Почати 7 днів безкоштовно';
$_['button_sms_balance']        = 'Перевірити ключ і баланс';
$_['button_sms_test']           = 'Надіслати тест';
$_['help_license_deactivate']  = 'Звільніть ключ, щоб перенести ліцензію на інший домен.';
$_['help_trial']               = 'Ключ на 7 днів прийде на цей e-mail і активується одразу. Автоматичних списань немає.';
$_['help_sms_gateway']         = 'TurboSMS — токен з розділу API; Alpha SMS — API-ключ у кабінеті; SMS-Fly — ключ з «Налаштування API».';
$_['help_sms_key']             = 'Зберігається у зашифрованому вигляді; порожнє поле = не змінювати.';
$_['help_sms_senders']         = 'По одному на рядок: код країни=імʼя, як його зареєстровано в сервісі. «*» — для решти країн. Країна визначається за номером і країною в замовленні.';
$_['help_sms_country']         = 'Для номерів без коду країни, якщо в замовленні країна не вказана. У сервіс номер завжди йде у форматі E.164.';
$_['help_sms_rules']           = 'Кожен статус надсилається покупцю один раз. Звичайний текст без розмітки; рядок, у якому всі теги порожні, пропускається.';
$_['help_sms_tags']            = '{comment} — коментар, доданий разом зі статусом; {tracking_number} — перше число з 11–14 цифр у цьому коментарі (номер ТТН).';
$_['help_sms_balance']         = 'Нічого не надсилає і нічого не коштує.';
$_['help_sms_test']            = 'Реальне повідомлення за тарифом вашого сервісу.';
$_['error_sms_gateway']        = 'Сервіс відхилив запит:';
$_['error_sms_pro']            = 'Тестове повідомлення доступне з активним Pro.';
$_['error_license_email']      = 'Введіть коректну адресу e-mail — на неї прийде ключ.';
$_['error_license_format']     = 'Введіть ключ ліцензії.';
$_['error_license_invalid']    = 'Ключ не знайдено. Перевірте, що скопіювали його повністю.';
$_['error_license_expired']    = '7-денний пробний період завершився. Придбайте ліцензію на catcode.com.ua.';
$_['error_license_limit']      = 'Цей ключ уже використовується в іншому магазині. Спершу звільніть його там.';
$_['error_license_product']    = 'Цей ключ належить іншому модулю CatCode.';
$_['error_trial_used']         = 'Пробний період для цього магазину вже видавався.';
$_['error_license_network']    = 'Не вдалося звʼязатися з сервером ліцензій. Спробуйте за кілька хвилин.';
$_['error_license_generic']    = 'Ключ не підходить.';
