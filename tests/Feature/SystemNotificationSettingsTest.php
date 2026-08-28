<?php

use App\Models\NotificationSettings;
use App\Models\User;
use App\Services\SystemNotificationService;
use App\Support\NotificationEventCatalog;

it('saves company notification settings', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'customers' => ['list'],
    ]);
    \App\Models\Group::query()->whereIn('id', $user->groups()->pluck('groups.id'))->update(['is_administrator' => true]);

    $response = $this->actingAs($user)->put(route('company-settings.update-notification-settings', $company), [
        'automation_enabled' => true,
        'events' => [
            'client_approval' => [
                'enabled' => true,
                'notify_admin' => false,
                'notify_client' => true,
                'notify_staff' => false,
            ],
            'contractor_approval' => [
                'enabled' => false,
                'notify_admin' => false,
                'notify_client' => true,
                'notify_staff' => false,
            ],
        ],
    ]);

    $response->assertRedirect();

    $settings = NotificationSettings::query()->where('company_id', $company->id)->firstOrFail();

    expect($settings->automation_enabled)->toBeTrue()
        ->and($settings->eventConfig('client_approval')['notify_client'])->toBeTrue()
        ->and($settings->eventConfig('contractor_approval')['enabled'])->toBeFalse();
});

it('sends contact information update details when applied', function () {
    $company = coverageCreateCompany(['email' => 'admin@example.com', 'name' => 'Revamp House']);
    NotificationSettings::create([
        'company_id' => $company->id,
        'automation_enabled' => true,
        'events' => NotificationEventCatalog::defaultEvents(),
    ]);

    $customer = coverageSeedCustomer($company, [
        'email' => 'client@example.com',
        'phone' => '0821111111',
    ]);

    $service = app(SystemNotificationService::class);
    $service->notifyClientInfoUpdateApplied($customer, $service->diffAttributes(
        ['phone' => '0821111111'],
        ['phone' => '0832222222'],
        ['phone' => 'Cell phone number']
    ));

    expect(NotificationSettings::getForCompany($company->id)->shouldSend('client_info_update_applied', 'client'))->toBeTrue();
});

it('does not send client approval email when disabled', function () {
    $company = coverageCreateCompany([
        'email' => 'admin@example.com',
    ]);

    NotificationSettings::create([
        'company_id' => $company->id,
        'automation_enabled' => true,
        'events' => array_merge(NotificationEventCatalog::defaultEvents(), [
            'client_approval' => [
                'enabled' => false,
                'notify_admin' => false,
                'notify_client' => true,
                'notify_staff' => false,
            ],
        ]),
    ]);

    $customer = coverageSeedCustomer($company);
    $client = User::factory()->create([
        'email' => 'client@example.com',
        'user_type' => 'client',
        'customer_id' => $customer->id,
        'approval_status' => 'pending',
    ]);

    app(SystemNotificationService::class)->notifyClientApproval($client);

    expect(NotificationSettings::getForCompany($company->id)->shouldSend('client_approval', 'client'))->toBeFalse();
});

it('formats contact information changes for notification emails', function () {
    $service = app(SystemNotificationService::class);

    $changes = $service->diffAttributes(
        ['phone' => '0821111111'],
        ['phone' => '0832222222'],
        ['phone' => 'Cell phone number']
    );

    expect($changes)->toHaveCount(1)
        ->and($changes[0]['label'])->toBe('Cell phone number')
        ->and($changes[0]['old'])->toBe('0821111111')
        ->and($changes[0]['new'])->toBe('0832222222');
});

it('renders system notification emails with company appearance branding', function () {
    $company = coverageCreateCompany([
        'name' => 'Branded Co',
        'theme_primary_hue' => 120,
        'theme_primary_saturation' => 70,
        'theme_primary_lightness' => 35,
        'theme_secondary_hue' => 200,
        'theme_secondary_saturation' => 60,
        'theme_secondary_lightness' => 40,
        'theme_accent_hue' => 45,
        'theme_accent_saturation' => 90,
        'theme_accent_lightness' => 50,
    ]);

    $html = view('emails.system-notification', [
        'company' => $company,
        'emailTitle' => 'Test notification',
        'bodyHtml' => '<p>Example body</p>',
    ])->render();

    $brand = $company->getEmailBranding();
    $changeHtml = app(SystemNotificationService::class)->renderChangeListHtml([
        ['label' => 'Phone', 'old' => '1', 'new' => '2'],
    ], $company);

    expect($html)
        ->toContain($brand['primary'])
        ->toContain($brand['secondary'])
        ->toContain('Branded Co')
        ->and($changeHtml)->toContain($brand['accent']);
});
