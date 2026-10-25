<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Models\AppointmentModel;
use App\Models\AppointmentServiceModel;
use App\Models\CustomerModel;
use App\Models\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use RuntimeException;

/**
 * Appointments screens of the web UI.
 *   GET  /app/appointments?date=         day list
 *   GET  /app/appointments/new           form (optionally ?customer_id=&date=)
 *   POST /app/appointments               create
 *   GET  /app/appointments/{id}          detail (items, payments, reminders, status actions)
 *   GET  /app/appointments/{id}/edit     form
 *   POST /app/appointments/{id}          update
 *   POST /app/appointments/{id}/status   {status, reason}
 *   POST /app/appointments/{id}/delete   only without payments
 */
class Appointments extends WebController
{
    public function index(): string
    {
        $date = $this->validDate((string) ($this->request->getGet('date') ?? ''));
        $day  = Time::parse($date);

        $rows = (new AppointmentModel())->forDay($date);

        return $this->render('web/appointments/index', [
            'title'        => lang('App.title_appointments'),
            'date'         => $date,
            'prev'         => $day->subDays(1)->toDateString(),
            'next'         => $day->addDays(1)->toDateString(),
            'appointments' => service('appointments')->attachRelations($rows),
        ], 'appointments');
    }

    public function create(): string
    {
        $customer = null;

        if ($cid = (int) $this->request->getGet('customer_id')) {
            $customer = (new CustomerModel())->find($cid);
        }

        $date = $this->validDate((string) ($this->request->getGet('date') ?? ''));

        return $this->renderForm(null, $customer, $date);
    }

    public function store(): RedirectResponse
    {
        $data = $this->input();

        try {
            $customerId = $this->resolveCustomer($data);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $startsAt = $this->startsAtFromForm($data);
        $items    = $this->itemsFromForm($data);

        if ($startsAt === null) {
            return redirect()->back()->withInput()->with('error', lang('App.fill_date_time'));
        }
        if ($items === []) {
            return redirect()->back()->withInput()->with('error', lang('App.select_one_service'));
        }

        $svc = service('appointments');

        try {
            $built  = $svc->buildItems($items);
            $endsAt = $svc->endsAt($startsAt, $built);

            if (empty($data['force'])) {
                $conflicts = $svc->conflicts($startsAt, $endsAt);

                if ($conflicts !== []) {
                    return redirect()->back()->withInput()->with('error', $this->conflictMessage($svc->attachRelations($conflicts)));
                }
            }

            $created = $svc->create([
                'customer_id' => $customerId,
                'starts_at'   => $startsAt,
                'discount'    => (int) ($data['discount'] ?? 0),
                'notes'       => trim((string) ($data['notes'] ?? '')) ?: null,
                'source'      => in_array($data['source'] ?? '', AppointmentModel::SOURCES, true) ? $data['source'] : null,
            ], $items);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $queued = (new \App\Models\MessageModel())->where('appointment_id', $created['id'])->where('type', 'booking')->countAllResults() > 0;

        return redirect()->to(site_url('app/appointments/' . $created['id']))->with('success', $queued ? lang('App.appointment_booked_wa') : lang('App.appointment_booked'));
    }

    public function show(int $id): string|RedirectResponse
    {
        $appointment = service('appointments')->withDetails($id);

        if ($appointment === null) {
            return redirect()->to(site_url('app/appointments'))->with('error', lang('App.appointment_not_found'));
        }

        $wa = service('whatsapp');

        foreach ($appointment['reminders'] as &$r) {
            $r['wa_link'] = $appointment['customer'] ? $wa->link($appointment['customer']['phone'], (string) $r['message']) : null;
        }

        return $this->render('web/appointments/show', [
            'title' => lang('App.title_appointment'),
            'a'     => $appointment,
        ], 'appointments');
    }

    public function edit(int $id): string|RedirectResponse
    {
        $appointment = (new AppointmentModel())->find($id);

        if ($appointment === null) {
            return redirect()->to(site_url('app/appointments'))->with('error', lang('App.appointment_not_found'));
        }

        $customer              = (new CustomerModel())->withDeleted()->find((int) $appointment['customer_id']);
        $appointment['items']  = (new AppointmentServiceModel())->forAppointment($id);

        return $this->renderForm($appointment, $customer, Time::parse($appointment['starts_at'])->toDateString());
    }

    public function update(int $id): RedirectResponse
    {
        $svc     = service('appointments');
        $current = (new AppointmentModel())->find($id);

        if ($current === null) {
            return redirect()->to(site_url('app/appointments'))->with('error', lang('App.appointment_not_found'));
        }

        $data     = $this->input();
        $startsAt = $this->startsAtFromForm($data) ?? $current['starts_at'];
        $items    = $this->itemsFromForm($data);

        if ($items === []) {
            return redirect()->back()->withInput()->with('error', lang('App.select_one_service'));
        }

        try {
            $customerId = $this->resolveCustomer($data, (int) $current['customer_id']);
            $built      = $svc->buildItems($items);
            $endsAt     = $svc->endsAt($startsAt, $built);

            if (empty($data['force'])) {
                $conflicts = $svc->conflicts($startsAt, $endsAt, $id);

                if ($conflicts !== []) {
                    return redirect()->back()->withInput()->with('error', $this->conflictMessage($svc->attachRelations($conflicts)));
                }
            }

            $svc->update($id, [
                'customer_id' => $customerId,
                'starts_at'   => $startsAt,
                'discount'    => (int) ($data['discount'] ?? 0),
                'notes'       => trim((string) ($data['notes'] ?? '')) ?: null,
                'source'      => in_array($data['source'] ?? '', AppointmentModel::SOURCES, true) ? $data['source'] : null,
            ], $items);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('app/appointments/' . $id))->with('success', lang('App.appointment_updated'));
    }

    public function status(int $id): RedirectResponse
    {
        $status = (string) $this->request->getPost('status');
        $reason = trim((string) $this->request->getPost('reason')) ?: null;

        if (! in_array($status, AppointmentModel::STATUSES, true)) {
            return $this->back('app/appointments')->with('error', lang('App.invalid_status'));
        }

        try {
            service('appointments')->changeStatus($id, $status, $reason);
        } catch (InvalidArgumentException $e) {
            return $this->back('app/appointments')->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('app/appointments/' . $id))->with('success', lang('App.status_changed', [status_label($status)]));
    }

    public function delete(int $id): RedirectResponse
    {
        $appointment = (new AppointmentModel())->find($id);

        if ($appointment === null) {
            return redirect()->to(site_url('app/appointments'))->with('error', lang('App.appointment_not_found'));
        }

        try {
            service('appointments')->delete($id);
        } catch (InvalidArgumentException $e) {
            return redirect()->to(site_url('app/appointments/' . $id))->with('error', $e->getMessage());
        }

        $date = Time::parse($appointment['starts_at'])->toDateString();

        return redirect()->to(site_url('app/appointments') . '?date=' . $date)->with('success', lang('App.appointment_deleted'));
    }

    // ------------------------------------------------------------------ helpers

    private function renderForm(?array $appointment, ?array $customer, string $date): string
    {
        $services = (new ServiceModel())->active();

        // selected service ids/qty: from old input (after a validation error), else from the appointment
        $selected = [];
        $oldSvc   = old('svc');

        if (is_array($oldSvc)) {
            foreach ($oldSvc as $sid => $qty) {
                $selected[(int) $sid] = max(1, (int) $qty);
            }
        } elseif ($appointment !== null) {
            foreach ($appointment['items'] as $item) {
                if ($item['service_id'] !== null) {
                    $selected[(int) $item['service_id']] = (int) $item['quantity'];
                }
            }
        }

        $time = '10:00';
        if ($appointment !== null) {
            $time = Time::parse($appointment['starts_at'])->format('H:i');
        }

        return $this->render('web/appointments/form', [
            'title'       => $appointment ? lang('App.title_edit_appointment') : lang('App.title_new_appointment'),
            'appointment' => $appointment,
            'customer'    => $customer,
            'services'    => $services,
            'selected'    => $selected,
            'date'        => old('date', $date),
            'time'        => old('time', $time),
            'sources'     => AppointmentModel::SOURCES,
        ], 'appointments');
    }

    /**
     * customer_id from the form, or a new/restored customer from new_name + new_phone.
     *
     * @throws InvalidArgumentException
     */
    private function resolveCustomer(array $data, ?int $fallback = null): int
    {
        $customers = new CustomerModel();

        if (! empty($data['customer_id'])) {
            if ($customers->find((int) $data['customer_id']) === null) {
                throw new InvalidArgumentException(lang('App.customer_not_found'));
            }

            return (int) $data['customer_id'];
        }

        $name  = trim((string) ($data['new_name'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($data['new_phone'] ?? ''));

        if ($name === '' && $phone === '') {
            if ($fallback !== null) {
                return $fallback;
            }

            throw new InvalidArgumentException(lang('App.select_or_add_customer'));
        }

        if ($name === '' || ! preg_match('/^\d{10}$/', $phone)) {
            throw new InvalidArgumentException(lang('App.new_customer_needs_name_phone'));
        }

        $existing = $customers->findByPhone($phone, true);

        if ($existing !== null) {
            if ($existing['deleted_at'] !== null) {
                $customers->restore((int) $existing['id']);
            }

            return (int) $existing['id'];
        }

        $id = $customers->insert(['name' => $name, 'phone' => $phone, 'whatsapp_opt_in' => 1]);

        if ($id === false) {
            throw new InvalidArgumentException(implode(' ', $customers->errors()));
        }

        return (int) $id;
    }

    /**
     * date (Y-m-d) + time (H:i) -> "Y-m-d H:i:s", or null when invalid.
     */
    private function startsAtFromForm(array $data): ?string
    {
        $date = (string) ($data['date'] ?? '');
        $time = (string) ($data['time'] ?? '');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }

        $value = $date . ' ' . $time . ':00';

        return strtotime($value) === false ? null : $value;
    }

    /**
     * svc[<service_id>] = quantity checkboxes -> items for AppointmentService::buildItems().
     *
     * @return list<array{service_id: int, quantity: int}>
     */
    private function itemsFromForm(array $data): array
    {
        $items = [];

        foreach ((array) ($data['svc'] ?? []) as $serviceId => $qty) {
            $qty = (int) $qty;

            if ((int) $serviceId > 0 && $qty > 0) {
                $items[] = ['service_id' => (int) $serviceId, 'quantity' => $qty];
            }
        }

        return $items;
    }

    private function conflictMessage(array $conflicts): string
    {
        $parts = [];

        foreach (array_slice($conflicts, 0, 3) as $c) {
            $parts[] = fmt_time($c['starts_at']) . '–' . fmt_time($c['ends_at']) . ' ' . ($c['customer']['name'] ?? '');
        }

        return lang('App.conflict_message', [implode(', ', $parts)]);
    }

    private function validDate(string $date): string
    {
        return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false) ? $date : Time::now()->toDateString();
    }
}
