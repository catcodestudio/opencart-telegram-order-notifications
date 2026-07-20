<?php
namespace Opencart\Catalog\Controller\Extension\CcTelegram;

require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/settings.php';
require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/notifier.php';

use Opencart\System\Library\CcTelegram\Settings;
use Opencart\System\Library\CcTelegram\Notifier;

/**
 * Second attempt at deliveries that failed — the usual cause is a blip at
 * api.telegram.org or a rate limit. Registered as
 * extension/cc_telegram/cron.retry.
 */
class Cron extends \Opencart\System\Engine\Controller {

	public function retry(): void {
		$settings = new Settings($this->config);
		if (!$settings->isEnabled() || !$settings->isConfigured()) {
			return;
		}
		if ((string)$settings->get('retry_enabled', '1') !== '1') {
			return;
		}

		$notifier = new Notifier($this->registry, $settings);
		$logger   = $notifier->logger();
		$maxAtt   = max(1, (int)$settings->get('max_attempts', 3));

		$this->load->model('checkout/order');

		foreach ($logger->retryable($maxAtt) as $row) {
			$order = $this->model_checkout_order->getOrder((int)$row['order_id']);
			if (!$order) {
				continue;
			}
			$notifier->retryRow($row, $order);
		}
	}
}
