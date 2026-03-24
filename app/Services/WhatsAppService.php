<?php

namespace App\Services;

use App\Support\SafeLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private $provider;

    private $apiKey;

    private $apiSecret;

    private $accountSid;

    private $fromNumber;

    private $defaultTemplateName;

    private $defaultTemplateLanguage;

    public function __construct($provider, $apiKey, $apiSecret = null, $accountSid = null, $fromNumber = null, $defaultTemplateName = null, $defaultTemplateLanguage = 'en')
    {
        $this->provider = $provider ?? 'twilio';
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->accountSid = $accountSid;
        $this->fromNumber = $fromNumber;
        $this->defaultTemplateName = $defaultTemplateName;
        $this->defaultTemplateLanguage = $defaultTemplateLanguage;
    }

    /**
     * Send a WhatsApp message
     *
     * @param  string  $to  Phone number (with country code, e.g., +27123456789)
     * @param  string|null  $templateName  Template name (required for Meta provider)
     * @param  array  $parameters  Named parameters for the template (e.g., ['customer_name' => 'John', 'invoice_number' => 'INV-001'])
     * @param  string  $templateLanguage  Language code (default: 'en')
     * @return array Response from WhatsApp API
     */
    public function sendMessage($to, $templateName = null, $parameters = [], $templateLanguage = 'en')
    {
        try {
            // Clean phone number (remove spaces, dashes, etc.)
            $to = $this->cleanPhoneNumber($to);

            // Validate phone number
            if (! $this->isValidPhoneNumber($to)) {
                throw new \Exception('Invalid phone number format');
            }

            Log::info('Sending WhatsApp message', SafeLog::redactContext([
                'to' => $to,
                'from' => $this->fromNumber,
                'provider' => $this->provider,
                'template_name' => $templateName ?? $this->defaultTemplateName,
                'parameter_keys' => array_keys($parameters),
            ]));

            // Route to appropriate provider
            switch ($this->provider) {
                case 'twilio':
                    // For Twilio, we still need a message body
                    throw new \Exception('Twilio provider requires message body. Use sendMessageWithBody() instead.');
                case 'meta':
                    // For Meta, template name is required
                    $templateName = $templateName ?? $this->defaultTemplateName;
                    if (! $templateName) {
                        throw new \Exception('Template name is required for Meta WhatsApp provider');
                    }
                    $language = $templateLanguage ?: $this->defaultTemplateLanguage ?: 'en';

                    return $this->sendViaMeta($to, $templateName, $parameters, $language);
                default:
                    throw new \Exception("Unsupported WhatsApp provider: {$this->provider}");
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp service error', SafeLog::redactContext([
                'to' => $to,
                'error' => $e->getMessage(),
            ]));

            return [
                'success' => false,
                'message' => 'WhatsApp service error: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Send WhatsApp message via Twilio
     */
    private function sendViaTwilio($to, $message): array
    {
        if (! $this->accountSid || ! $this->apiSecret) {
            throw new \Exception('Twilio credentials not configured');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $response = Http::asForm()
            ->withBasicAuth($this->accountSid, $this->apiSecret)
            ->timeout(30)
            ->post($url, [
                'From' => "whatsapp:{$this->fromNumber}",
                'To' => "whatsapp:{$to}",
                'Body' => $message,
            ]);

        $responseData = $response->json();

        if ($response->successful()) {
            Log::info('WhatsApp message sent successfully via Twilio', SafeLog::redactContext([
                'to' => $to,
                'response_excerpt' => SafeLog::excerpt(json_encode($responseData ?: []), 200),
            ]));

            return [
                'success' => true,
                'message' => 'WhatsApp message sent successfully',
                'data' => $responseData,
            ];
        } else {
            Log::error('WhatsApp sending failed via Twilio', SafeLog::redactContext([
                'to' => $to,
                'http_status' => $response->status(),
                'response_excerpt' => SafeLog::excerpt(json_encode($responseData ?: []), 300),
            ]));

            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp message: '.($responseData['message'] ?? 'Unknown error'),
                'data' => $responseData,
            ];
        }
    }

    /**
     * Send WhatsApp message via Meta (WhatsApp Business API)
     * Uses template messages with named parameters
     */
    private function sendViaMeta($to, $templateName, $parameters = [], $templateLanguage = 'en'): array
    {
        if (! $this->apiKey) {
            throw new \Exception('Meta API credentials not configured');
        }

        // Remove + from phone number for Meta API
        $to = ltrim($to, '+');

        // Use v22.0 API version (as shown in Meta's example)
        $url = "https://graph.facebook.com/v22.0/{$this->fromNumber}/messages";

        // Meta WhatsApp requires template messages only
        if (! $templateName) {
            throw new \Exception('Template name is required for Meta WhatsApp provider');
        }

        // Build template payload
        // Convert 'en' to 'en_US' for Meta API compatibility
        $languageCode = $templateLanguage === 'en' ? 'en_US' : $templateLanguage;

        $template = [
            'name' => $templateName,
            'language' => [
                'code' => 'en',
            ],
        ];

        // Add named parameters if provided
        if (! empty($parameters)) {
            $bodyParams = [];
            foreach ($parameters as $paramName => $paramValue) {
                $bodyParams[] = [
                    'type' => 'text',
                    'parameter_name' => $paramName,
                    'text' => (string) $paramValue,
                ];
            }

            if (! empty($bodyParams)) {
                $template['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => $bodyParams,
                    ],
                ];
            }
        }

        // Use template message
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post($url, $payload);

        $responseData = $response->json();

        if ($response->successful()) {
            Log::info('WhatsApp message sent successfully via Meta', SafeLog::redactContext([
                'to' => $to,
                'type' => 'template',
                'template' => $templateName,
                'parameter_keys' => array_keys($parameters),
                'response_excerpt' => SafeLog::excerpt(json_encode($responseData ?: []), 200),
            ]));

            return [
                'success' => true,
                'message' => 'WhatsApp message sent successfully',
                'data' => $responseData,
            ];
        } else {
            Log::error('WhatsApp sending failed via Meta', SafeLog::redactContext([
                'to' => $to,
                'http_status' => $response->status(),
                'response_excerpt' => SafeLog::excerpt(json_encode($responseData ?: []), 300),
            ]));

            $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
            $errorCode = $responseData['error']['code'] ?? null;

            // Provide helpful error messages for common Meta API errors
            if ($errorCode == 131030) {
                $errorMessage = 'Recipient phone number not in allowed list. Please add this number to your allowed recipients in Meta Business Manager (Settings > WhatsApp > API Setup > Manage phone number list).';
            } elseif ($errorCode == 131026) {
                $errorMessage = 'Invalid recipient phone number format. Please ensure the number includes country code (e.g., +27123456789).';
            } elseif ($errorCode == 131047) {
                $errorMessage = 'Message template not found or not approved. Please check your template name and approval status in Meta Business Manager.';
            } elseif ($errorCode == 131051) {
                $errorMessage = 'Free-form messages can only be sent within 24 hours of customer contact. Please use a template message for initial conversations.';
            } elseif ($errorCode == 132000) {
                $errorMessage = 'Number of parameters does not match the expected number of params. Your template may not have parameters defined, or the number of parameters sent does not match what the template expects. Please check your template configuration in Meta Business Manager.';
            }

            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp message: '.$errorMessage,
                'data' => $responseData,
            ];
        }
    }

    /**
     * Clean phone number by removing spaces, dashes, and other characters
     */
    private function cleanPhoneNumber($phoneNumber): string
    {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);

        // If it doesn't start with +, assume it's a South African number and add +27
        if (! str_starts_with($cleaned, '+')) {
            // Remove leading 0 if present
            if (str_starts_with($cleaned, '0')) {
                $cleaned = substr($cleaned, 1);
            }
            $cleaned = '+27'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Validate phone number format
     */
    private function isValidPhoneNumber($phoneNumber): bool
    {
        // Basic validation for international format
        return preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber);
    }
}
