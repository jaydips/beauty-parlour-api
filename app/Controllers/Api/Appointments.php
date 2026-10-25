<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 *   GET    /appointments?date=Y-m-d | ?from=&to=  [&status=&customer_id=&page=]
 *   POST   /appointments  {customer_id, starts_at, items:[{service_id, quantity?} | {service_name, price, duration_minutes?}], discount?, notes?, source?, force?}
 *   GET    /appointments/{id}
 *   PUT    /appointments/{id}            same fields as POST, all optional; items replace the list
 *   PATCH  /appointments/{id}/status     {status, reason?}
 *   DELETE /appointments/{id}            only when it has no payments
 *
 * Double bookings answer 409 with the conflicting appointments unless force=true.
 */
class Appointments extends ApiController
{
    public function index(): ResponseInterface
    {
        $model = new AppointmentModel();
        [$page, $perPage] = $this->pageParams();

        $from = $this->request->getGet('from');
        $to   = $this->request->getGet('to');

        if ($from || $to) {
            if ($from) { $model->where('starts_at >=', $this->dateParam('from') . ' 00:00:00'); }
            if ($to)   { $model->where('starts_at <=', $this->dateParam('to') . ' 23:59:59'); }
        } else {
            $date = $this->dateParam('date');
            $model->where('starts_at >=', $date . ' 00:00:00')->where('starts_at <=', $date . ' 23:59:59');
        }

        if ($status = $this->request->getGet('status')) {
            $model->whereIn('status', array_intersect(explode(',', (string) $status), AppointmentModel::STATUSES));
        }
        if ($customerId = (int) $this->request->getGet('customer_id')) {
            $model->where('customer_id', $customerId);
        }

        $rows = $model->orderBy('starts_at', 'ASC')->paginate($perPage, 'default', $page);

        return $this->respond([
            'appointments' => service('appointments')->attachRelations($rows),
            'pager'        => $this->pagerInfo($model->pager),
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $row = service('appointments')->withDetails($id);

        return $row === null ? $this->failNotFound(lang('App.appointment_not_found')) : $this->respond(['appointment' => $row]);
    }

    public function create(): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'customer_id' => 'required|is_natural_no_zero',
            'starts_at'   => 'required|valid_date[Y-m-d H:i:s]',
            'items'       => 'required',
            'discount'    => 'permit_empty|is_natural',
            'notes'       => 'permit_empty|string',
            'source'      => 'permit_empty|in_list[walk_in,phone,whatsapp,app]',
            'status'      => 'permit_empty|in_list[scheduled,confirmed]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        if ((new CustomerModel())->find((int) $data['customer_id']) === null) {
            return $this->failValidationErrors(['customer_id' => lang('App.customer_not_found')]);
        }

        $svc = service('appointments');

        try {
            $items = $svc->buildItems(is_array($data['items']) ? $data['items'] : []);
        } catch (InvalidArgumentException $e) {
            return $this->failValidationErrors(['items' => $e->getMessage()]);
        }

        $endsAt = $svc->endsAt($data['starts_at'], $items);

        if (! filter_var($data['force'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $conflicts = $svc->conflicts($data['starts_at'], $endsAt);

            if ($conflicts !== []) {
                return $this->fail(['message' => lang('App.api_slot_overlaps'), 'conflicts' => $svc->attachRelations($conflicts)], 409);
            }
        }

        try {
            $created = $svc->create($data, $data['items']);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->respondCreated(['appointment' => $svc->withDetails($created['id'])]);
    }

    public function update(int $id): ResponseInterface
    {
        $svc     = service('appointments');
        $current = (new AppointmentModel())->find($id);

        if ($current === null) {
            return $this->failNotFound(lang('App.appointment_not_found'));
        }

        $data  = $this->input();
        $rules = [
            'customer_id' => 'permit_empty|is_natural_no_zero',
            'starts_at'   => 'permit_empty|valid_date[Y-m-d H:i:s]',
            'discount'    => 'permit_empty|is_natural',
            'notes'       => 'permit_empty|string',
            'source'      => 'permit_empty|in_list[walk_in,phone,whatsapp,app]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        if (! empty($data['customer_id']) && (new CustomerModel())->find((int) $data['customer_id']) === null) {
            return $this->failValidationErrors(['customer_id' => lang('App.customer_not_found')]);
        }

        $itemsInput = array_key_exists('items', $data) && is_array($data['items']) ? $data['items'] : null;

        try {
            $items    = $itemsInput !== null ? $svc->buildItems($itemsInput) : (new \App\Models\AppointmentServiceModel())->forAppointment($id);
            $startsAt = $data['starts_at'] ?? $current['starts_at'];
            $endsAt   = $svc->endsAt($startsAt, $items);

            if (! filter_var($data['force'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $conflicts = $svc->conflicts($startsAt, $endsAt, $id);

                if ($conflicts !== []) {
                    return $this->fail(['message' => lang('App.api_slot_overlaps_save'), 'conflicts' => $svc->attachRelations($conflicts)], 409);
                }
            }

            $svc->update($id, $data, $itemsInput);
        } catch (InvalidArgumentException $e) {
            return $this->failValidationErrors(['items' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->respond(['appointment' => $svc->withDetails($id)]);
    }

    public function status(int $id): ResponseInterface
    {
        $data  = $this->input();
        $rules = [
            'status' => 'required|in_list[scheduled,confirmed,completed,cancelled,no_show]',
            'reason' => 'permit_empty|string|max_length[255]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->validationFailure();
        }

        $svc = service('appointments');

        try {
            $svc->changeStatus($id, $data['status'], $data['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->failNotFound($e->getMessage());
        }

        return $this->respond(['appointment' => $svc->withDetails($id)]);
    }

    public function delete(int $id): ResponseInterface
    {
        if ((new AppointmentModel())->find($id) === null) {
            return $this->failNotFound(lang('App.appointment_not_found'));
        }

        try {
            service('appointments')->delete($id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 409);
        }

        return $this->respondDeleted(['deleted' => $id]);
    }
}
