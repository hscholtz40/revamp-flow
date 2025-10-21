<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BulkSMSService
{
    private $username;
    private $password;
    private $senderName;
    private $baseUrl = 'https://api.bulksms.com/v1/messages';

    public function __construct($username, $password, $senderName = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->senderName = $senderName;
    }

    /**
     * Send an SMS message
     *
     * @param string $to Phone number (with country code, e.g., +27123456789)
     * @param string $message SMS message content
     * @return array Response from BulkSMS API
     */
    public function sendSMS($to, $message)
    {
        try {
            // Clean phone number (remove spaces, dashes, etc.)
            $to = $this->cleanPhoneNumber($to);

            // Validate phone number
            if (!$this->isValidPhoneNumber($to)) {
                throw new \Exception('Invalid phone number format');
            }

            // Prepare the request data
            $data = [
                'to' => $to,
                'body' => $message,
            ];

            // Add sender name if provided
            if ($this->senderName) {
                $data['from'] = $this->senderName;
            }

            Log::info('Sending SMS via BulkSMS', [
                'to' => $to,
                'message_length' => strlen($message),
                'sender' => $this->senderName,
            ]);

            // Make the API request
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(30)
                ->post($this->baseUrl, $data);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('SMS sent successfully', [
                    'to' => $to,
                    'response' => $responseData,
                ]);

                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'data' => $responseData,
                ];
            } else {
                Log::error('SMS sending failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send SMS: ' . ($responseData['error']['message'] ?? 'Unknown error'),
                    'data' => $responseData,
                ];
            }

        } catch (\Exception $e) {
            Log::error('SMS service error', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS service error: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Clean phone number by removing spaces, dashes, and other characters
     *
     * @param string $phoneNumber
     * @return string
     */
    private function cleanPhoneNumber($phoneNumber)
    {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);

        // If it doesn't start with +, assume it's a South African number and add +27
        if (!str_starts_with($cleaned, '+')) {
            // Remove leading 0 if present
            if (str_starts_with($cleaned, '0')) {
                $cleaned = substr($cleaned, 1);
            }
            $cleaned = '+27' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Validate phone number format
     *
     * @param string $phoneNumber
     * @return bool
     */
    private function isValidPhoneNumber($phoneNumber)
    {
        // Basic validation for international format
        return preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber);
    }

    /**
     * Get account balance (if supported by API)
     *
     * @return array
     */
    public function getBalance()
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(30)
                ->get('https://api.bulksms.com/v1/account/balance');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to get balance',
                    'data' => $response->json(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting balance: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
