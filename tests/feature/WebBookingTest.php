<?php

declare(strict_types=1);

use App\Database\Seeds\SettingsSeeder;
use App\Libraries\OwnerAuth;
use App\Models\OwnerModel;
use App\Models\ServiceModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Web UI booking flow: customer form -> appointment form (conflict, force) -> detail -> status -> payment.
 *
 * @internal
 */
final class WebBookingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = SettingsSeeder::class;

    private array $ownerSession = ['owner_id' => 1, 'owner_name' => 'Jalpa Patel'];

    protected function setUp(): void
    {
        parent::setUp();
        (new OwnerModel())->saveOwner(['name' => 'Jalpa Patel', 'phone' => '9033319266', 'password_hash' => OwnerAuth::hashPassword('secret123')]);
        (new ServiceModel())->insert(['name' => 'Haircut', 'duration_minutes' => 45, 'price' => 500]);
        (new ServiceModel())->insert(['name' => 'Facial', 'duration_minutes' => 60, 'price' => 1200]);
    }

    public function testCustomerSearchRequiresSessionAndReturnsJson(): void
    {
        $this->get('app/customers/search?q=as')->assertStatus(302);

        $this->withSession($this->ownerSession)->post('customers-not-a-route', [])->assertStatus(404);

        $this->withSession($this->ownerSession)->post('app/customers', $this->csrf(['name' => 'Asha Mehta', 'phone' => '98765 43211', 'whatsapp_opt_in' => '1']))->assertStatus(302);
        $this->seeInDatabase('customers', ['phone' => '9876543211']);

        $res = $this->withSession($this->ownerSession)->get('app/customers/search?q=asha');
        $res->assertStatus(200);
        $rows = json_decode($res->getJSON(), true);
        $this->assertSame('Asha Mehta', $rows[0]['name']);

        $list = $this->withSession($this->ownerSession)->get('app/customers?q=9876');
        $list->assertStatus(200);
        $list->assertSee('Asha Mehta');
    }

    public function testBookingFlowThroughForms(): void
    {
        $day = Time::now()->addDays(2)->toDateString();

        // new customer created from the appointment form itself
        $res = $this->withSession($this->ownerSession)->post('app/appointments', $this->csrf([
            'customer_id' => '', 'new_name' => 'Rina', 'new_phone' => '9000000002',
            'date' => $day, 'time' => '11:00', 'svc' => [1 => '1', 2 => '1'], 'discount' => '100', 'source' => 'phone',
        ]));
        $res->assertStatus(302);
        $this->assertMatchesRegularExpression('#/app/appointments/\d+$#', (string) $res->getRedirectUrl());
        $id = (int) basename((string) $res->getRedirectUrl());
        $this->seeInDatabase('customers', ['phone' => '9000000002', 'name' => 'Rina']);
        $this->seeInDatabase('appointments', ['id' => $id, 'total' => 1600, 'ends_at' => $day . ' 12:45:00']);

        $show = $this->withSession($this->ownerSession)->get("app/appointments/{$id}");
        $show->assertStatus(200);
        $show->assertSee('Rina');
        $show->assertSee('Facial');
        $show->assertSee('1,600');

        // overlapping slot is refused unless forced
        // the CSRF hash regenerates after every request, so build it per call
        $overlap  = ['customer_id' => '', 'new_name' => 'Meena', 'new_phone' => '9000000003', 'date' => $day, 'time' => '12:00', 'svc' => [1 => '1']];
        $conflict = $this->withSession($this->ownerSession)->post('app/appointments', $this->csrf($overlap));
        $conflict->assertStatus(302);
        $this->assertDoesNotMatchRegularExpression('#/app/appointments/\d+$#', (string) $conflict->getRedirectUrl());

        $forced = $this->withSession($this->ownerSession)->post('app/appointments', $this->csrf($overlap + ['force' => '1']));
        $forced->assertStatus(302);
        $this->assertMatchesRegularExpression('#/app/appointments/\d+$#', (string) $forced->getRedirectUrl());

        // day list shows both
        $list = $this->withSession($this->ownerSession)->get('app/appointments?date=' . $day);
        $list->assertStatus(200);
        $list->assertSee('Rina');
        $list->assertSee('Meena');

        // complete + payment via the detail page forms
        $this->withSession($this->ownerSession)->post("app/appointments/{$id}/status", $this->csrf(['status' => 'completed']))->assertStatus(302);
        $this->seeInDatabase('appointments', ['id' => $id, 'status' => 'completed']);
        $this->seeInDatabase('reminders', ['appointment_id' => $id, 'type' => 'follow_up', 'status' => 'pending']);

        $pay = $this->withSession($this->ownerSession)->post('app/payments', $this->csrf(['appointment_id' => (string) $id, 'amount' => '1000', 'method' => 'upi']));
        $pay->assertStatus(302);
        $this->seeInDatabase('payments', ['appointment_id' => $id, 'amount' => 1000, 'method' => 'upi']);

        $show = $this->withSession($this->ownerSession)->get("app/appointments/{$id}");
        $show->assertSee('600'); // balance

        // appointment with payments cannot be deleted
        $this->withSession($this->ownerSession)->post("app/appointments/{$id}/delete", $this->csrf([]))->assertStatus(302);
        $this->seeInDatabase('appointments', ['id' => $id]);
    }

    public function testEditFormAndCustomerPages(): void
    {
        $this->withSession($this->ownerSession)->post('app/customers', $this->csrf(['name' => 'Asha', 'phone' => '9876543211']))->assertStatus(302);
        $customerId = 1;

        $form = $this->withSession($this->ownerSession)->get('app/appointments/new?customer_id=' . $customerId);
        $form->assertStatus(200);
        $form->assertSee('Asha · 9876543211');
        $form->assertSee('Haircut');

        $detail = $this->withSession($this->ownerSession)->get("app/customers/{$customerId}");
        $detail->assertStatus(200);
        $detail->assertSee('https://wa.me/919876543211', null);

        $this->withSession($this->ownerSession)->post("app/customers/{$customerId}", $this->csrf(['name' => 'Asha Mehta', 'phone' => '9876543211', 'notes' => 'allergy: none']))->assertStatus(302);
        $this->seeInDatabase('customers', ['id' => $customerId, 'name' => 'Asha Mehta']);

        $this->withSession($this->ownerSession)->post("app/customers/{$customerId}/delete", $this->csrf([]))->assertStatus(302);
        $this->dontSeeInDatabase('customers', ['id' => $customerId, 'deleted_at' => null]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function csrf(array $data): array
    {
        return $data + [csrf_token() => csrf_hash()];
    }
}
