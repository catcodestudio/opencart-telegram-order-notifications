<?php
namespace Opencart\System\Library\CcTelegram;

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/client.php';
require_once __DIR__ . '/formatter.php';
require_once __DIR__ . '/logger.php';

/**
 * Renders a message once and fans it out to every configured chat, journaling
 * each delivery. Shared by the catalog event controller, the retry cron and the
 * admin "send test message" button.
 */
class Notifier {

	private Settings $settings;
	private Formatter $formatter;
	private Logger $logger;
	private Client $client;

	public function __construct($registry, Settings $settings) {
		$this->settings  = $settings;
		$this->formatter = new Formatter($registry, $settings);
		$this->logger    = new Logger($registry->get('db'));
		$this->client    = new Client((string)$settings->get('bot_token', ''));
	}

	public function logger(): Logger {
		return $this->logger;
	}

	public function formatter(): Formatter {
		return $this->formatter;
	}

	public function client(): Client {
		return $this->client;
	}

	/**
	 * Announce a new order once, the first time it lands in one of the
	 * configured statuses.
	 *
	 * @return bool Whether the new-order message was sent by this call.
	 */
	public function maybeNewOrder(array $order, int $statusId): bool {
		if ((string)$this->settings->get('notify_new_order', '0') !== '1') {
			return false;
		}
		$orderId = (int)($order['order_id'] ?? 0);
		if ($orderId <= 0 || $this->logger->newOrderSent($orderId)) {
			return false;
		}
		if (!in_array($statusId, $this->settings->statusList('new_order_statuses'), true)) {
			return false;
		}

		// Claim the order before sending, so a second event in the same request
		// (or a parallel one) cannot produce a duplicate announcement.
		$this->logger->markNewOrderSent($orderId);

		$this->dispatchOrder($order, 'new_order');
		return true;
	}

	/**
	 * Announce a status change. The previous status is passed in because the
	 * order row already carries the new one.
	 */
	public function statusChanged(array $order, int $oldStatusId, int $newStatusId): void {
		if ((string)$this->settings->get('notify_status', '0') !== '1') {
			return;
		}
		if (!in_array($newStatusId, $this->settings->statusList('status_statuses'), true)) {
			return;
		}
		$this->dispatchOrder($order, 'status', [
			'order_status_old' => $this->formatter->orderStatusName($oldStatusId),
		]);
	}

	public function lowStock(array $product, string $storeUrl): void {
		$text = $this->formatter->product($product, $storeUrl);
		if (trim($text) === '') {
			return;
		}
		$this->broadcast(0, (int)($product['product_id'] ?? 0), 'low_stock', $text);
	}

	/** Free-form message (test button). @return array{sent:int,errors:string[]} */
	public function broadcastRaw(string $event, string $text): array {
		return $this->broadcast(0, 0, $event, $text);
	}

	/**
	 * Re-render an order message from the live order and resend it to one chat.
	 * Used by the retry cron, so a message reflects reality at send time rather
	 * than at failure time.
	 */
	public function retryRow(array $row, array $order): void {
		$event = (string)$row['event'];
		$text  = $this->formatter->order($order, $this->templateFor($event));
		if (trim($text) === '') {
			return;
		}

		$res = $this->client->sendMessage(
			(string)$row['chat_id'],
			$text,
			$this->threadFor((string)$row['chat_id']),
			$this->settings->isSilent()
		);

		$this->logger->resolve((int)$row['id'], (bool)$res['ok'], (int)$res['status'], $res['ok'] ? 'OK' : (string)$res['error']);
	}

	/**
	 * Render once, then fan out to every chat.
	 *
	 * @param array<string,string> $extra Extra placeholder values.
	 * @return array{sent:int,errors:string[]}
	 */
	public function dispatchOrder(array $order, string $event, array $extra = []): array {
		$text = $this->formatter->order($order, $this->templateFor($event), $extra);
		if (trim($text) === '') {
			return ['sent' => 0, 'errors' => []];
		}
		return $this->broadcast((int)($order['order_id'] ?? 0), 0, $event, $text);
	}

	/** @return array{sent:int,errors:string[]} */
	private function broadcast(int $orderId, int $productId, string $event, string $text): array {
		$silent = $this->settings->isSilent();
		$sent   = 0;
		$errors = [];

		foreach ($this->settings->chats() as $chat) {
			$res = $this->client->sendMessage((string)$chat['chat_id'], $text, (int)$chat['thread_id'], $silent);

			$this->logger->log(
				$orderId,
				$productId,
				$event,
				(string)$chat['chat_id'],
				(int)$res['status'],
				$res['ok'] ? 'OK' : (string)$res['error'],
				(bool)$res['ok']
			);

			if ($res['ok']) {
				++$sent;
			} else {
				$errors[] = $chat['chat_id'] . ': ' . $res['error'];
			}
		}

		$this->logger->prune();

		return ['sent' => $sent, 'errors' => $errors];
	}

	private function threadFor(string $chatId): int {
		foreach ($this->settings->chats() as $chat) {
			if ((string)$chat['chat_id'] === $chatId) {
				return (int)$chat['thread_id'];
			}
		}
		return 0;
	}

	private function templateFor(string $event): string {
		switch ($event) {
			case 'new_order':
				return (string)$this->settings->get('template_new_order', '');
			case 'low_stock':
				return (string)$this->settings->get('template_low_stock', '');
			case 'status':
			case 'failed':
			default:
				return (string)$this->settings->get('template_status', '');
		}
	}
}
