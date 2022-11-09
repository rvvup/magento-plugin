<?php declare(strict_types=1);

namespace Rvvup\Payments\Api;

use Rvvup\Payments\Api\Data\WebhookInterface;

interface WebhookRepositoryInterface
{
    /**
     * @param array $data
     * @return WebhookInterface
     */
    public function new(array $data = []): WebhookInterface;

    /**
     * @param WebhookInterface $webhook
     * @return WebhookInterface
     */
    public function save(WebhookInterface $webhook): WebhookInterface;

    /**
     * @param int $id
     * @return WebhookInterface
     */
    public function getById(int $id): WebhookInterface;

    /**
     * @param WebhookInterface $webhook
     * @return WebhookInterface
     */
    public function delete(WebhookInterface $webhook): WebhookInterface;
}
