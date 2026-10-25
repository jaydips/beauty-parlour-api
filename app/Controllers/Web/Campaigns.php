<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\CampaignModel;
use App\Models\MessageModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use RuntimeException;

/**
 * Offers / announcements to many customers through the WhatsApp bridge.
 *   GET  /app/campaigns            list
 *   GET  /app/campaigns/new?days=  form with recipient counts per audience
 *   POST /app/campaigns            create + queue one message per recipient (optional picture, optional start time)
 *   GET  /app/campaigns/{id}       progress and per-recipient status
 *   POST /app/campaigns/{id}/cancel
 */
class Campaigns extends WebController
{
    public function index(): string
    {
        return $this->render('web/campaigns/index', [
            'title'     => lang('App.title_campaigns'),
            'campaigns' => service('outbox')->decorateCampaigns((new CampaignModel())->orderBy('id', 'DESC')->findAll(100)),
        ], 'more');
    }

    public function create(): string
    {
        $outbox = service('outbox');
        $days   = max(1, min(3650, (int) ($this->request->getGet('days') ?: 90)));

        return $this->render('web/campaigns/form', [
            'title'       => lang('App.title_new_campaign'),
            'days'        => $days,
            'counts'      => [
                'all'      => count($outbox->recipients('all')),
                'recent'   => count($outbox->recipients('recent', $days)),
                'inactive' => count($outbox->recipients('inactive', $days)),
            ],
            'autoSend'    => $outbox->autoSendEnabled(),
            'defaultBody' => service('whatsapp')->template('offer'),
        ], 'more');
    }

    public function store(): RedirectResponse
    {
        $data     = $this->input();
        $title    = trim((string) ($data['title'] ?? ''));
        $body     = trim((string) ($data['body'] ?? ''));
        $audience = (string) ($data['audience'] ?? 'all');
        $days     = max(1, min(3650, (int) ($data['days'] ?? 90)));

        if (! in_array($audience, CampaignModel::AUDIENCES, true)) {
            $audience = 'all';
        }
        if ($title === '' || mb_strlen($title) > 120) {
            return redirect()->back()->withInput()->with('error', lang('App.offer_name_required'));
        }
        if ($body === '' || mb_strlen($body) > 2000) {
            return redirect()->back()->withInput()->with('error', lang('App.message_required_2000'));
        }

        // optional start: date + time fields; empty = now
        $start     = Time::now();
        $startDate = (string) ($data['start_date'] ?? '');
        $startTime = (string) ($data['start_time'] ?? '');

        if ($startDate !== '' || $startTime !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || ! preg_match('/^\d{2}:\d{2}$/', $startTime) || strtotime("{$startDate} {$startTime}:00") === false) {
                return redirect()->back()->withInput()->with('error', lang('App.fill_date_time'));
            }

            $start = Time::parse("{$startDate} {$startTime}:00");
        }

        $image  = $this->request->getFile('image');
        $images = service('campaignImage');

        if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            try {
                if ($image->getError() !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException(lang('App.image_invalid'));
                }
                $images->validate($image->getTempName(), (int) $image->getSize());
            } catch (InvalidArgumentException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        } else {
            $image = null;
        }

        try {
            $result = service('outbox')->createCampaign($title, $body, $audience, $audience === 'all' ? null : $days, $start);

            if ($image !== null) {
                service('outbox')->setCampaignImage($result['id'], $images->store($image->getTempName(), $result['id']));
            }
        } catch (RuntimeException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $note = service('outbox')->autoSendEnabled() ? '' : lang('App.campaign_auto_off_note');

        return redirect()->to(site_url('app/campaigns/' . $result['id']))
            ->with('success', lang('App.campaign_queued', [(int) $result['recipients']]) . $note);
    }

    public function show(int $id): string
    {
        $campaign = (new CampaignModel())->find($id);

        if ($campaign === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $messages = new MessageModel();

        return $this->render('web/campaigns/show', [
            'title'    => $campaign['title'],
            'campaign' => service('outbox')->decorateCampaigns([$campaign])[0],
            'counts'   => $messages->counts(['campaign_id' => $id]),
            'messages' => service('outbox')->decorate($messages->where('campaign_id', $id)->orderBy('id', 'ASC')->findAll(300)),
        ], 'more');
    }

    public function cancel(int $id): RedirectResponse
    {
        if ((new CampaignModel())->find($id) === null) {
            return redirect()->to(site_url('app/campaigns'))->with('error', lang('App.campaign_not_found'));
        }

        $n = service('outbox')->cancelCampaign($id);

        return redirect()->to(site_url('app/campaigns/' . $id))->with('success', lang('App.campaign_cancelled', [$n]));
    }
}
