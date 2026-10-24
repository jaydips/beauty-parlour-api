<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CustomerModel;
use App\Models\MessageModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * WhatsApp outbox (what the bridge sent, will send or could not send).
 *   GET  /messages?status=queued,sent,failed,cancelled&type=&campaign_id=&customer_id=&page=
 *   POST /messages {customer_id, body, scheduled_at?}   queue a custom message (sent when auto-send is on)
 *   GET  /messages/{id}
 *   POST /messages/{id}/retry     failed/cancelled -> queued now
 *   POST /messages/{id}/cancel    queued -> cancelled
 */
class Messages extends ApiController
{
    public function index(): ResponseInterface
    {
        $model = new MessageModel();
        [$page, $perPage] = $this->pageParams();

        $status = (string) ($this->request->getGet('status') ?? 'all');
        if ($status !== 'all') {
            $model->whereIn('status', array_intersect(explode(',', $status), MessageModel::STATUSES) ?: MessageModel::STATUSES);
        }
        if ($type = $this->request->getGet('type')) {
            $model->whereIn('type', array_intersect(explode(',', (string) $type), MessageModel::TYPES) ?: MessageModel::TYPES);
        }
        if ($campaignId = (int) $this->request->getGet('campaign_id')) {
            $model->where('campaign_id', $campaignId);
        }
        if ($customerId = (int) $this->request->getGet('customer_id')) {
            $model->where('customer_id', $customerId);
        }

        $rows = $model->orderBy('id', 'DESC')->paginate($perPage, 'default', $page);

        return $this->respond([
            'messages' => service('outbox')->decorate($rows),
            'pager'    => $this->pagerInfo($model->pager),
            'counts'   => (new MessageModel())->counts(),
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new MessageModel())->find($id);

        return $row === null ? $this->failNotFound('Message not found.') : $this->respond(['message' => service('outbox')->decorate([$row])[0]]);
    }

    public function create(): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'customer_id'  => 'required|is_natural_no_zero',
            'body'         => 'required|string|max_length[2000]',
            'scheduled_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        $customer = (new CustomerModel())->find((int) $data['customer_id']);

        if ($customer === null) {
            return $this->failValidationErrors(['customer_id' => 'Customer not found.']);
        }

        $id = service('outbox')->enqueue($customer, 'custom', (string) $data['body'], [], ! empty($data['scheduled_at']) ? (string) $data['scheduled_at'] : null);

        if ($id === null) {
            return $this->failValidationErrors(['customer_id' => 'Customer has no usable 10-digit phone.']);
        }

        return $this->respondCreated([
            'message'   => service('outbox')->decorate([(new MessageModel())->find($id)])[0],
            'auto_send' => service('outbox')->autoSendEnabled(),
        ]);
    }

    public function retry(int $id): ResponseInterface
    {
        $model = new MessageModel();
        $row   = $model->find($id);

        if ($row === null) {
            return $this->failNotFound('Message not found.');
        }
        if (! in_array($row['status'], ['failed', 'cancelled'], true)) {
            return $this->fail('Only failed or cancelled messages can be retried.', 409);
        }

        $model->requeue($id);

        return $this->respond(['message' => service('outbox')->decorate([$model->find($id)])[0]]);
    }

    public function cancel(int $id): ResponseInterface
    {
        $model = new MessageModel();
        $row   = $model->find($id);

        if ($row === null) {
            return $this->failNotFound('Message not found.');
        }
        if ($row['status'] !== 'queued') {
            return $this->fail('Only queued messages can be cancelled.', 409);
        }

        $model->cancel($id);

        if (! empty($row['campaign_id'])) {
            service('outbox')->refreshCampaign((int) $row['campaign_id']);
        }

        return $this->respond(['message' => service('outbox')->decorate([$model->find($id)])[0]]);
    }
}
