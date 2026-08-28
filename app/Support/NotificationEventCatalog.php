<?php

namespace App\Support;

class NotificationEventCatalog
{
    /**
     * @return array<string, array{label: string, description: string, default: array{enabled: bool, notify_admin: bool, notify_client: bool, notify_staff: bool}}>
     */
    public static function events(): array
    {
        return [
            'client_registration' => [
                'label' => 'Client Zone sign-up',
                'description' => 'When a client registers for Client Zone and is pending approval.',
                'default' => ['enabled' => true, 'notify_admin' => true, 'notify_client' => false, 'notify_staff' => false],
            ],
            'client_approval' => [
                'label' => 'Client Zone approval',
                'description' => 'When a pending Client Zone user is approved.',
                'default' => ['enabled' => true, 'notify_admin' => false, 'notify_client' => true, 'notify_staff' => false],
            ],
            'client_rejection' => [
                'label' => 'Client Zone rejection',
                'description' => 'When a pending Client Zone registration is rejected.',
                'default' => ['enabled' => true, 'notify_admin' => false, 'notify_client' => true, 'notify_staff' => false],
            ],
            'client_info_update_request' => [
                'label' => 'Contact information update request',
                'description' => 'When a client submits a request to update their contact information.',
                'default' => ['enabled' => true, 'notify_admin' => true, 'notify_client' => false, 'notify_staff' => false],
            ],
            'client_info_update_applied' => [
                'label' => 'Contact information update applied',
                'description' => 'When an approved contact information update is applied to the customer record.',
                'default' => ['enabled' => true, 'notify_admin' => false, 'notify_client' => true, 'notify_staff' => false],
            ],
            'client_info_update_rejected' => [
                'label' => 'Contact information update rejected',
                'description' => 'When a contact information update request is rejected.',
                'default' => ['enabled' => true, 'notify_admin' => false, 'notify_client' => true, 'notify_staff' => false],
            ],
            'contractor_signup' => [
                'label' => 'Contractor sign-up',
                'description' => 'When a new contractor onboarding enquiry is submitted.',
                'default' => ['enabled' => true, 'notify_admin' => true, 'notify_client' => false, 'notify_staff' => false],
            ],
            'contractor_approval' => [
                'label' => 'Contractor approval',
                'description' => 'When a contractor sign-up is approved.',
                'default' => ['enabled' => true, 'notify_admin' => false, 'notify_client' => true, 'notify_staff' => false],
            ],
            'customer_updated' => [
                'label' => 'Customer record updated',
                'description' => 'When staff update a customer record in the system.',
                'default' => ['enabled' => false, 'notify_admin' => true, 'notify_client' => true, 'notify_staff' => false],
            ],
            'contact_updated' => [
                'label' => 'Contact updated',
                'description' => 'When staff update a customer contact record.',
                'default' => ['enabled' => false, 'notify_admin' => true, 'notify_client' => true, 'notify_staff' => false],
            ],
            'supplier_updated' => [
                'label' => 'Supplier updated',
                'description' => 'When staff update a supplier record.',
                'default' => ['enabled' => false, 'notify_admin' => true, 'notify_client' => false, 'notify_staff' => false],
            ],
            'product_updated' => [
                'label' => 'Product updated',
                'description' => 'When staff update a product catalog item.',
                'default' => ['enabled' => false, 'notify_admin' => true, 'notify_client' => false, 'notify_staff' => false],
            ],
            'service_updated' => [
                'label' => 'Service updated',
                'description' => 'When staff update a service catalog item.',
                'default' => ['enabled' => false, 'notify_admin' => true, 'notify_client' => false, 'notify_staff' => false],
            ],
            'staff_assignment' => [
                'label' => 'Staff assignment',
                'description' => 'When a jobcard or task is assigned to a user or team.',
                'default' => ['enabled' => false, 'notify_admin' => false, 'notify_client' => false, 'notify_staff' => true],
            ],
        ];
    }

    public static function defaultEvents(): array
    {
        return collect(self::events())
            ->mapWithKeys(fn (array $event, string $key) => [$key => $event['default']])
            ->all();
    }
}
