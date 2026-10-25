<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\CampaignModel;
use App\Models\MessageModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use RuntimeException;

/**
 * Offer / announcement broadcasts for the Flutter app.
 *   GET  /campaigns?page=                                           list (each row carries image_url)
 *   GET  /campaigns/preview?audience=all|recent|inactive&days=90   recipient count + sample names
 *   POST /campaigns {title, body, audience, days?, scheduled_at?}   JSON, or multipart/form-data with an
 *                                                                   optional `image` file (jpeg/png/webp <= 2 MB,
 *                                                                   stored as JPEG, sent with every message)
 *   GET  /campaigns/{id}                                            campaign + counts + per-recipient messages
 *   POST /campaigns/{id}/cancel
 * Body placeholders: {customer_name}, {parlour_name}, {parlour_address}, {parlour_phone}, ...
 * Nothing goes out while settings.wa_auto_send is "0", before scheduled_at, or before the link warm-up.
 */
class Campaigns extends ApiController
{
    public function index(): ResponseInterface
    {
        $model = new CampaignModel();
        [$page, $perPage] = $this->pageParams();

        $rows = $model->orderBy('id', 'DESC')->paginate($perPage, 'default', $page);

        return $this->respond(['campaigns' => service('outbox')->decorateCampaigns($rows), 'pager' => $this->pagerInfo($model->pager)]);
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
            'default_body' => service('whatsapp')->template('offer'), // settings.tpl_whatsapp_offer, Gujarati by default
        ]);
    }

    public function create(): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'title'        => 'required|string|max_length[120]',
            'body'         => 'required|string|max_length[2000]',
            'audience'     => 'permit_empty|in_list[all,recent,inactive]',
            'days'         => 'permit_empty|is_natural_no_zero',
            'scheduled_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        [$audience, $days] = $this->audienceParams($data);
        $start = ! empty($data['scheduled_at']) ? Time::parse((string) $data['scheduled_at']) : Time::now();

        // the picture is checked before anything is queued, so a bad upload leaves no half campaign
        $image  = $this->request->getFile('image');
        $images = service('campaignImage');

        if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            // error code only (isValid() also wants is_uploaded_file(), which no test request can satisfy);
            // the bytes themselves are checked by CampaignImage::validate()
            if ($image->getError() !== UPLOAD_ERR_OK) {
                return $this->failValidationErrors(['image' => lang('App.image_invalid')]);
            }

            try {
                $images->validate($image->getTempName(), (int) $image->getSize());
            } catch (InvalidArgumentException $e) {
                return $this->failValidationErrors(['image' => $e->getMessage()]);
            }
        } else {
            $image = null;
        }

        try {
            $result = service('outbox')->createCampaign(trim((string) $data['title']), trim((string) $data['body']), $audience, $days, $start);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        if ($image !== null) {
            try {
                service('outbox')->setCampaignImage($result['id'], $images->store($image->getTempName(), $result['id']));
            } catch (InvalidArgumentException $e) {
                return $this->failValidationErrors(['image' => $e->getMessage()]);
            }
        }

        return $this->respondCreated($this->payload($result['id']) + ['auto_send' => service('outbox')->autoSendEnabled()]);
    }

    public function show(int $id): ResponseInterface
    {
        if ((new CampaignModel())->find($id) === null) {
            return $this->failNotFound(lang('App.campaign_not_found'));
        }

        return $this->respond($this->payload($id));
    }

    public function cancel(int $id): ResponseInterface
    {
        if ((new CampaignModel())->find($id) === null) {
            return $this->failNotFound(lang('App.campaign_not_found'));
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
            'campaign' => service('outbox')->decorateCampaigns([(new CampaignModel())->find($id)])[0],
            'counts'   => $messages->counts(['campaign_id' => $id]),
            'messages' => service('outbox')->decorate($messages->where('campaign_id', $id)->orderBy('id', 'ASC')->findAll(300)),
        ];
    }
}
