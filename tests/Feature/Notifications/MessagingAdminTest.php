<?php

namespace Tests\Feature\Notifications;

use App\Enums\SmsTemplateKey;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_reword_and_switch_off_sms_templates()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/sms-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/sms-templates')
                ->has('templates', count(SmsTemplateKey::cases()))
                ->where('templates.0.key', 'application_status')
                ->where('templates.0.placeholders', ['name', 'opportunity', 'status', 'remarks']));

        $this->put('/admin/sms-templates/application_status', ['body' => 'JoBauko: {opportunity} - {status}', 'is_active' => false])
            ->assertSessionHasNoErrors();

        $template = SmsTemplate::for(SmsTemplateKey::ApplicationStatus);
        $this->assertSame('JoBauko: {opportunity} - {status}', $template->body);
        $this->assertFalse($template->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sms_template.updated', 'auditable_id' => $template->id]);
    }

    public function test_templates_only_accept_their_own_placeholders()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/sms-templates/deadline_reminder', ['body' => 'Hi {name}, {status}', 'is_active' => true])
            ->assertSessionHasErrors('body');
        $this->put('/admin/sms-templates/deadline_reminder', ['body' => str_repeat('a', SmsTemplate::MAX_LENGTH + 1), 'is_active' => true])
            ->assertSessionHasErrors('body');
        $this->put('/admin/sms-templates/not_a_template', ['body' => 'x', 'is_active' => true])->assertNotFound();
    }

    public function test_delivery_logs_list_sms_and_email_with_masked_numbers()
    {
        $admin = User::factory()->admin()->create();
        $seeker = User::factory()->seeker()->create(['mobile_number' => '09171234567']);
        SmsLog::create(['user_id' => $seeker->id, 'mobile_number' => '09171234567', 'message' => 'Hello', 'topic' => 'status_updates', 'status' => 'failed', 'error' => 'No credits']);
        SmsLog::create(['user_id' => $seeker->id, 'mobile_number' => '09171234567', 'message' => 'Hello again', 'topic' => 'deadlines', 'status' => 'sent']);

        $this->actingAs($admin)
            ->get('/admin/delivery-logs?status=failed')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/delivery-logs')
                ->has('logs.data', 1)
                ->where('logs.data.0.error', 'No credits')
                ->where('logs.data.0.address', fn ($address) => ! str_contains($address, '1234567'))
                ->where('counts.sent', 1));

        $this->get('/admin/delivery-logs?channel=email')->assertInertia(fn ($page) => $page->where('filters.channel', 'email')->has('logs.data', 0));
    }

    public function test_only_admins_see_messaging_pages()
    {
        $this->actingAs(User::factory()->seeker()->create());

        $this->get('/admin/sms-templates')->assertForbidden();
        $this->get('/admin/delivery-logs')->assertForbidden();
    }
}
