<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerUpdateRequest;
use App\Models\NotificationSettings;
use App\Models\Query;
use App\Models\User;
use App\Support\CompanyMailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SystemNotificationService
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'address' => 'Address',
        'city' => 'City',
        'country' => 'Country',
        'state' => 'State/Province',
        'postal_code' => 'Postal code',
        'vat_number' => 'VAT number',
        'notes' => 'Notes',
        'company_cell' => 'Cell phone number',
        'company_tel' => 'Telephone number',
        'registration_number' => 'Registration number',
        'contact_first_name' => 'Contact first name',
        'contact_last_name' => 'Contact last name',
        'contact_cell' => 'Contact cell',
        'contact_email' => 'Contact email',
        'position' => 'Position',
        'sku' => 'SKU',
        'price' => 'Price',
        'cost' => 'Cost',
        'unit' => 'Unit',
        'category' => 'Category',
        'description' => 'Description',
        'is_active' => 'Active status',
    ];

    public function settingsForCompany(int $companyId): NotificationSettings
    {
        return NotificationSettings::getForCompany($companyId);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, array{label: string, old: string, new: string}>
     */
    public function diffAttributes(array $before, array $after, array $fieldLabels = []): array
    {
        $labels = array_merge(self::FIELD_LABELS, $fieldLabels);
        $changes = [];

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            if ($this->normalizeValue($oldValue) === $this->normalizeValue($newValue)) {
                continue;
            }

            $changes[] = [
                'label' => $labels[$field] ?? ucfirst(str_replace('_', ' ', (string) $field)),
                'old' => $this->formatValue($oldValue),
                'new' => $this->formatValue($newValue),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<int, array{label: string, old: string, new: string}>  $changes
     */
    public function renderChangeListHtml(array $changes, ?Company $company = null): string
    {
        if ($changes === []) {
            return '<p>No field changes were detected.</p>';
        }

        $items = collect($changes)
            ->map(fn (array $change) => '<li style="margin-bottom: 8px;"><strong>'.e($change['label']).'</strong> changed from '
                .e($change['old'] ?: '—').' to '.e($change['new'] ?: '—').'.</li>')
            ->implode('');

        $list = '<ul style="margin: 0; padding-left: 20px;">'.$items.'</ul>';

        if ($company === null) {
            return $list;
        }

        $brand = $company->getEmailBranding();

        return '<div style="background-color: '.$brand['surface'].'; border-left: 4px solid '.$brand['accent'].'; padding: 16px; margin: 16px 0;">'
            .$list
            .'</div>';
    }

    private function brandedLink(Company $company, string $url, string $label): string
    {
        $primary = e($company->getEmailBranding()['primary']);

        return '<a href="'.e($url).'" style="color: '.$primary.'; font-weight: 600; text-decoration: underline;">'.e($label).'</a>';
    }

    public function notifyClientRegistration(Company $company, Customer $customer, string $clientName): void
    {
        if (! $this->settingsForCompany((int) $company->id)->shouldSend('client_registration', 'admin')) {
            return;
        }

        $pendingUrl = route('registered-users.pending.index');
        $subject = "New Client Zone registration pending approval: {$clientName}";
        $body = '<p>A new Client Zone user has registered and is awaiting approval.</p>'
            .'<p><strong>Customer:</strong> '.e($customer->name).'<br>'
            .'<strong>Client name:</strong> '.e($clientName).'</p>'
            .'<p>'.$this->brandedLink($company, $pendingUrl, 'Review pending registrations').'</p>';

        $this->sendToAdmins($company, 'client_registration', $subject, $body);
    }

    public function notifyClientApproval(User $clientUser): void
    {
        $clientUser->loadMissing('customer.company');
        $company = $clientUser->customer?->company;
        if ($company === null) {
            return;
        }

        if (! $this->settingsForCompany((int) $company->id)->shouldSend('client_approval', 'client')) {
            return;
        }

        $loginUrl = URL::to('/login');
        $companyName = e($company->name);
        $subject = 'Your Client Zone access has been approved';
        $body = '<p>Hello '.e($clientUser->name).',</p>'
            ."<p>Your sign-up for Client Zone with {$companyName} has been approved.</p>"
            .'<p>You can now sign in using the link below:</p>'
            .'<p>'.$this->brandedLink($company, $loginUrl, 'Sign in to Client Zone').'</p>'
            ."<p>If you have any questions, please contact {$companyName}.</p>";

        $this->sendToAddress($company, $clientUser->email, $clientUser->name, 'client_approval', $subject, $body);
    }

    public function notifyClientRejection(User $clientUser, ?string $reason = null): void
    {
        $clientUser->loadMissing('customer.company');
        $company = $clientUser->customer?->company;
        if ($company === null) {
            return;
        }

        if (! $this->settingsForCompany((int) $company->id)->shouldSend('client_rejection', 'client')) {
            return;
        }

        $companyName = e($company->name);
        $reasonHtml = $reason ? '<p><strong>Reason:</strong> '.nl2br(e($reason), false).'</p>' : '';
        $subject = 'Your Client Zone registration was not approved';
        $body = '<p>Hello '.e($clientUser->name).',</p>'
            ."<p>Your registration for Client Zone with {$companyName} was not approved.</p>"
            .$reasonHtml
            ."<p>If you have questions, please contact {$companyName}.</p>";

        $this->sendToAddress($company, $clientUser->email, $clientUser->name, 'client_rejection', $subject, $body);
    }

    public function notifyClientInfoUpdateRequest(Customer $customer, CustomerUpdateRequest $updateRequest, User $clientUser): void
    {
        $customer->loadMissing('company');
        $company = $customer->company;
        if ($company === null) {
            return;
        }

        if (! $this->settingsForCompany((int) $company->id)->shouldSend('client_info_update_request', 'admin')) {
            return;
        }

        $reviewUrl = route('registered-users.update-requests.show', $updateRequest);
        $changes = $this->diffAttributes(
            $customer->only(array_keys($updateRequest->requested_changes ?? [])),
            $updateRequest->requested_changes ?? []
        );

        $subject = 'Client Zone: contact information update request';
        $body = '<p>A client submitted a request to update their contact information.</p>'
            .'<p><strong>Customer:</strong> '.e($customer->name).'<br>'
            .'<strong>Client account:</strong> '.e($clientUser->name).'</p>'
            .$this->renderChangeListHtml($changes, $company)
            .'<p>'.$this->brandedLink($company, $reviewUrl, 'Review request').'</p>';

        $this->sendToAdmins($company, 'client_info_update_request', $subject, $body);
    }

    public function notifyClientInfoUpdateApplied(Customer $customer, array $changes): void
    {
        $customer->loadMissing('company');
        $company = $customer->company;
        if ($company === null) {
            return;
        }

        if (! $this->settingsForCompany((int) $company->id)->shouldSend('client_info_update_applied', 'client')) {
            return;
        }

        $companyName = e($company->name);
        $subject = 'Your contact information was updated';
        $body = '<p>Hello '.e($customer->name).',</p>'
            .'<p>The following contact information was updated in your account:</p>'
            .$this->renderChangeListHtml($changes, $company)
            ."<p>If this information is incorrect, please contact {$companyName}.</p>";

        $this->sendToAddress($company, $customer->email, $customer->name, 'client_info_update_applied', $subject, $body);
    }

    public function notifyClientInfoUpdateRejected(Customer $customer, ?string $reason): void
    {
        $customer->loadMissing('company');
        $company = $customer->company;
        if ($company === null) {
            return;
        }

        if (! $this->settingsForCompany((int) $company->id)->shouldSend('client_info_update_rejected', 'client')) {
            return;
        }

        $companyName = e($company->name);
        $reasonHtml = $reason ? '<p><strong>Reason:</strong> '.nl2br(e($reason), false).'</p>' : '';
        $subject = 'Your contact information update was not approved';
        $body = '<p>Hello '.e($customer->name).',</p>'
            .'<p>Your request to update your contact information was not approved.</p>'
            .$reasonHtml
            ."<p>If you have questions, please contact {$companyName}.</p>";

        $this->sendToAddress($company, $customer->email, $customer->name, 'client_info_update_rejected', $subject, $body);
    }

    public function notifyContractorSignup(Company $company, Query $query): void
    {
        if (! $this->settingsForCompany((int) $company->id)->shouldSend('contractor_signup', 'admin')) {
            return;
        }

        try {
            $mailConfig = CompanyMailer::resolve($company);
            $subject = "New contractor query submitted (#{$query->id})";

            Mail::mailer($mailConfig['mailer'])->send('emails.query-notification', [
                'company' => $company,
                'query' => $query,
            ], function ($message) use ($company, $mailConfig, $subject) {
                $message->to($company->email, $company->name)
                    ->subject($subject)
                    ->from($mailConfig['from_address'], $mailConfig['from_name']);

                if (is_string($company->email) && filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
                    $message->replyTo($company->email, $company->name ?: null);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send contractor signup notification email.', [
                'company_id' => $company->id,
                'query_id' => $query->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyContractorApproval(Company $company, Query $query): void
    {
        if (! $this->settingsForCompany((int) $company->id)->shouldSend('contractor_approval', 'client')) {
            return;
        }

        $to = trim((string) ($query->company_email ?: $query->email));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $loginUrl = URL::to('/login');
        $companyName = e($company->name);
        $contactName = e(trim($query->name.' '.$query->surname));
        $subject = 'Your contractor sign-up has been approved';
        $body = '<p>Hello '.$contactName.',</p>'
            ."<p>Your sign-up as a contractor with {$companyName} has been approved.</p>"
            .'<p>Please use the link below to create your username and password and start personalising your system:</p>'
            .'<p>'.$this->brandedLink($company, $loginUrl, 'Set up your account').'</p>'
            ."<p>If you need help, please contact {$companyName}.</p>";

        $this->sendToAddress($company, $to, $contactName, 'contractor_approval', $subject, $body);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function notifyCustomerUpdated(Customer $customer, array $before, array $after): void
    {
        $customer->loadMissing('company');
        $company = $customer->company;
        if ($company === null) {
            return;
        }

        $changes = $this->diffAttributes($before, $after);
        if ($changes === []) {
            return;
        }

        $settings = $this->settingsForCompany((int) $company->id);
        $subject = 'Your customer record was updated';
        $body = '<p>Hello '.e($customer->name).',</p>'
            .'<p>Your customer record at '.e($company->name).' was updated with the following changes:</p>'
            .$this->renderChangeListHtml($changes, $company)
            .'<p>If this information is incorrect, please contact '.e($company->name).'.</p>';

        if ($settings->shouldSend('customer_updated', 'client')) {
            $this->sendToAddress($company, $customer->email, $customer->name, 'customer_updated', $subject, $body);
        }

        if ($settings->shouldSend('customer_updated', 'admin')) {
            $this->sendToAdmins($company, 'customer_updated', 'Customer record updated: '.$customer->name, $body);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function notifyContactUpdated(Company $company, string $contactName, ?string $contactEmail, array $before, array $after): void
    {
        $changes = $this->diffAttributes($before, $after);
        if ($changes === []) {
            return;
        }

        $settings = $this->settingsForCompany((int) $company->id);
        $subject = 'Your contact information was updated';
        $body = '<p>Hello '.e($contactName).',</p>'
            .'<p>Your contact information at '.e($company->name).' was updated:</p>'
            .$this->renderChangeListHtml($changes, $company)
            .'<p>If this information is incorrect, please contact '.e($company->name).'.</p>';

        if ($settings->shouldSend('contact_updated', 'client') && $contactEmail) {
            $this->sendToAddress($company, $contactEmail, $contactName, 'contact_updated', $subject, $body);
        }

        if ($settings->shouldSend('contact_updated', 'admin')) {
            $this->sendToAdmins($company, 'contact_updated', 'Contact updated: '.$contactName, $body);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function notifySupplierUpdated(Company $company, string $supplierName, array $before, array $after): void
    {
        if (! $this->settingsForCompany((int) $company->id)->shouldSend('supplier_updated', 'admin')) {
            return;
        }

        $changes = $this->diffAttributes($before, $after);
        if ($changes === []) {
            return;
        }

        $subject = 'Supplier updated: '.$supplierName;
        $body = '<p>The supplier <strong>'.e($supplierName).'</strong> was updated with the following changes:</p>'
            .$this->renderChangeListHtml($changes, $company);

        $this->sendToAdmins($company, 'supplier_updated', $subject, $body);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function notifyCatalogItemUpdated(Company $company, string $itemName, string $itemType, array $before, array $after): void
    {
        $eventKey = $itemType === 'service' ? 'service_updated' : 'product_updated';
        if (! $this->settingsForCompany((int) $company->id)->shouldSend($eventKey, 'admin')) {
            return;
        }

        $changes = $this->diffAttributes($before, $after);
        if ($changes === []) {
            return;
        }

        $label = $itemType === 'service' ? 'Service' : 'Product';
        $subject = "{$label} updated: {$itemName}";
        $body = '<p>The '.$label.' <strong>'.e($itemName).'</strong> was updated with the following changes:</p>'
            .$this->renderChangeListHtml($changes, $company);

        $this->sendToAdmins($company, $eventKey, $subject, $body);
    }

    public function notifyStaffAssignment(Company $company, User $user, string $subject, string $body, ?string $actionUrl = null): void
    {
        if (! $this->settingsForCompany((int) $company->id)->shouldSend('staff_assignment', 'staff')) {
            return;
        }

        if ($actionUrl) {
            $body .= '<p>'.$this->brandedLink($company, $actionUrl, 'Open in JobCard Online').'</p>';
        }

        $this->sendToAddress($company, $user->email, $user->name, 'staff_assignment', $subject, $body);
    }

    public function sendToAdmins(Company $company, string $eventKey, string $subject, string $bodyHtml): void
    {
        foreach ($this->adminRecipientEmails($company) as $recipient) {
            $this->sendToAddress($company, $recipient['email'], $recipient['name'], $eventKey, $subject, $bodyHtml);
        }
    }

    /**
     * @return array<int, array{email: string, name: string}>
     */
    public function adminRecipientEmails(Company $company): array
    {
        $recipients = [];

        if (is_string($company->email) && filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['email' => $company->email, 'name' => $company->name ?: 'Company'];
        }

        $adminUsers = User::query()
            ->staffSelectableForCompany((int) $company->id)
            ->get()
            ->filter(fn (User $user) => $user->isAdministrator() && filter_var((string) $user->email, FILTER_VALIDATE_EMAIL));

        foreach ($adminUsers as $admin) {
            $recipients[] = ['email' => $admin->email, 'name' => $admin->name];
        }

        return collect($recipients)->unique('email')->values()->all();
    }

    private function sendToAddress(
        Company $company,
        ?string $email,
        ?string $name,
        string $eventKey,
        string $subject,
        string $bodyHtml,
    ): void {
        $to = trim((string) $email);
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $mailConfig = CompanyMailer::resolve($company);
            Mail::mailer($mailConfig['mailer'])->send('emails.system-notification', [
                'company' => $company,
                'bodyHtml' => $bodyHtml,
                'emailTitle' => $subject,
            ], function ($message) use ($to, $name, $subject, $company, $mailConfig) {
                $message->to($to, $name ?: null)
                    ->subject($subject)
                    ->from($mailConfig['from_address'], $mailConfig['from_name']);

                if (is_string($company->email) && filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
                    $message->replyTo($company->email, $company->name ?: null);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('System notification email failed', [
                'company_id' => $company->id,
                'event' => $eventKey,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) ($value ?? ''));
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }
}
