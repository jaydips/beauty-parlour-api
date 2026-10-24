<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\CampaignModel;
use App\Models\MessageModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * Offers / announcements to many customers through the WhatsApp bridge.
 *   GET  /app/campaigns            list
 *   GET  /app/campaigns/new?days=  form with recipient counts per audience
 *   POST /app/campaigns            create + queue one message per recipient
 *   GET  /app/campaigns/{id}       progress and per-recipient status
 *   POST /app/campaigns/{id}/cancel
 */
class Campaigns extends WebController
{
    public function index(): string
    {
        return $this->render('web/campaigns/index', [
            'title'     => 'Offers / broadcast',
            'campaigns' => (new CampaignModel())->orderBy('id', 'DESC')->findAll(100),
        ], 'more');
    }

    public function create(): string
    {
        $outbox = service('outbox');
        $days   = max(1, min(3650, (int) ($this->request->getGet('days') ?: 90)));

        return $this->render('web/campaigns/form', [
            'title'    => 'નવી offer / broadcast',
            'days'     => $days,
            'counts'   => [
                'all'      => count($outbox->recipients('all')),
                'recent'   => count($outbox->recipients('recent', $days)),
                'inactive' => count($outbox->recipients('inactive', $days)),
            ],
            'autoSend' => $outbox->autoSendEnabled(),
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
            return redirect()->back()->withInput()->with('error', 'Offer નું નામ લખો (120 અક્ષર સુધી).');
        }
        if ($body === '' || mb_strlen($body) > 2000) {
            return redirect()->back()->withInput()->with('error', 'Message લખો (2000 અક્ષર સુધી).');
        }

        try {
            $result = service('outbox')->createCampaign($title, $body, $audience, $audience === 'all' ? null : $days);
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $note = service('outbox')->autoSendEnabled() ? '' : ' Auto-send બંધ છે: WhatsApp page પરથી ચાલુ કરશો ત્યારે મોકલાશે.';

        return redirect()->to(site_url('app/campaigns/' . $result['id']))
            ->with('success', $result['recipients'] . ' customers માટે message કતારમાં મૂક્યા.' . $note);
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
            'campaign' => $campaign,
            'counts'   => $messages->counts(['campaign_id' => $id]),
            'messages' => service('outbox')->decorate($messages->where('campaign_id', $id)->orderBy('id', 'ASC')->findAll(300)),
        ], 'more');
    }

    public function cancel(int $id): RedirectResponse
    {
        if ((new CampaignModel())->find($id) === null) {
            return redirect()->to(site_url('app/campaigns'))->with('error', 'Campaign મળ્યું નહીં.');
        }

        $n = service('outbox')->cancelCampaign($id);

        return redirect()->to(site_url('app/campaigns/' . $id))->with('success', "Campaign રદ: {$n} બાકી messages રદ થયા.");
    }
}
