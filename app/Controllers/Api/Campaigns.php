<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CampaignModel;
use App\Models\MessageModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Offers / announcements sent to many customers through the WhatsApp bridge.
 *   GET  /campaigns?page=
 *   GET  /campaigns/preview?audience=all|recent|inactive&days=90   recipient count + sample names
 *   POST /campaigns {title, body, audience, days?}                  creates and queues one message per recipient
 *   GET  /campaigns/{id}                                            campaign + counts + per-recipient messages
 *   POST /campaigns/{id}/cancel
 * Body placeholders: {customer_name}, {parlour_name}. Nothing goes out while settings.wa_auto_send is "0".
 */
class Campaigns extends ApiController
{
    public function index(): ResponseInterface
    {
        $model = new CampaignModel();
        [$page, $perPage] = $this->pageParams();

        $rows = $model->orderBy('id', 'DESC')->paginate($perPage, 'default', $page);

        return $this->respond(['campaigns' => $rows, 'pager' => $this->pagerInfo($model->pager)]);
    }

    public function preview(): ResponseInterface
    {
        [$audience, $days] = $this->audienceParams($this->request->getGet());
        $recipients        = service('outbox')->recipients($audience, $days);

        return $this->respond([
            'audience'   => $audience,
            'days'       => $audience === 'all' ? null : $days,
            'recipients' => count($recipients),
            'sample'     => array_map(static fn (array $c): array => ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone']], array_slice($recipients, 0, 5)),
            'auto_send'  => service('outbox')->autoSendEnabled(),
        ]);
    }

    public function create(): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'title'    => 'required|string|max_length[120]',
            'body'     => 'required|string|max_length[2000]',
            'audience' => 'permit_empty|in_list[all,recent,inactive]',
            'days'     => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        [$audience, $days] = $this->audienceParams($data);

        try {
            $result = service('outbox')->createCampaign(trim((string) $data['title']), trim((string) $data['body']), $audience, $days);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->respondCreated($this->payload($result['id']) + ['auto_send' => service('outbox')->autoSendEnabled()]);
    }

    public function show(int $id): ResponseInterface
    {
        if ((new CampaignModel())->find($id) === null) {
            return $this->failNotFound('Campaign not found.');
        }

        return $this->respond($this->payload($id));
    }

    public function cancel(int $id): ResponseInterface
    {
        if ((new CampaignModel())->find($id) === null) {
            return $this->failNotFound('Campaign not found.');
        }

        $cancelled = service('outbox')->cancelCampaign($id);

        return $this->respond($this->payload($id) + ['cancelled' => $cancelled]);
    }

    /**
     * @return array{0: string, 1: ?int}
     */
    private function audienceParams(array $data): array
    {
        $audience = (string) ($data['audience'] ?? 'all');
        $days     = max(1, min(3650, (int) ($data['days'] ?? 90)));

        if (! in_array($audience, CampaignModel::AUDIENCES, true)) {
            $audience = 'all';
        }

        return [$audience, $audience === 'all' ? null : $days];
    }

    private function payload(int $id): array
    {
        $messages = new MessageModel();

        return [
            'campaign' => (new CampaignModel())->find($id),
            'counts'   => $messages->counts(['campaign_id' => $id]),
            'messages' => service('outbox')->decorate($messages->where('campaign_id', $id)->orderBy('id', 'ASC')->findAll(300)),
        ];
    }
}
