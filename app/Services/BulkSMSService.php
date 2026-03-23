<?php

namespace App\Services;

use App\Support\SafeLog;
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
     * @param  string  $to  Phone number (with country code, e.g., +27123456789)
     * @param  string  $message  SMS message content
     * @return array Response from BulkSMS API
     */
    public function sendSMS($to, $message)
    {
        try {
            // Clean phone number (remove spaces, dashes, etc.)
            $to = $this->cleanPhoneNumber($to);

            // Validate phone number
            if (! $this->isValidPhoneNumber($to)) {
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

            Log::info('Sending SMS via BulkSMS', SafeLog::redactContext([
                'to' => $to,
                'message_length' => strlen($message),
                'sender' => $this->senderName,
            ]));

            // Make the API request
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(30)
                ->post($this->baseUrl, $data);

            $responseData = $response->json();

            // Check HTTP status first
            if (! $response->successful()) {
                Log::error('SMS sending failed - HTTP error', SafeLog::redactContext([
                    'to' => $to,
                    'http_status' => $response->status(),
                    'response_excerpt' => SafeLog::excerpt(json_encode($responseData ?: []), 300),
                ]));

                return [
                    'success' => false,
                    'message' => 'Failed to send SMS: '.($responseData['error']['message'] ?? 'HTTP '.$response->status()),
                    'data' => $responseData,
                ];
            }

            // Check the actual message status in the response body
            // BulkSMS API returns an array of message objects
            $messages = is_array($responseData) ? $responseData : [$responseData];
            $hasFailure = false;
            $failureMessages = [];

            foreach ($messages as $message) {
                // Check if the message has a status field indicating failure
                if (isset($message['status'])) {
                    $statusType = $message['status']['type'] ?? null;
                    $statusId = $message['status']['id'] ?? null;

                    // Check for failure statuses
                    if ($statusType === 'FAILED' ||
                        (is_string($statusId) && str_contains(strtoupper($statusId), 'FAILED')) ||
                        (is_string($statusId) && str_contains(strtoupper($statusId), 'NOT_SENT'))) {
                        $hasFailure = true;
                        $failureMessages[] = $message['status']['id'] ?? 'Unknown failure';
                    }
                }
            }

            if ($hasFailure) {
                $errorMessage = 'SMS failed: '.implode(', ', $failureMessages);
                Log::error('SMS sending failed - message status indicates failure', SafeLog::redactContext([
                    'to' => $to,
                    'response_excerpt' => SafeLog::excerpt(json_encode($responseData ?: []), 300),
                    'failure_statuses' => $failureMessages,
                ]));

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'data' => $responseData,
                ];
            }

            // Success - HTTP status is OK and message status indicates success
            Log::info('SMS sent successfully', SafeLog::redactContext([
                'to' => $to,
                'response_excerpt' => SafeLog::excerpt(json_encode($responseData ?: []), 200),
            ]));

            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'data' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('SMS service error', SafeLog::redactContext([
                'to' => $to,
                'error' => $e->getMessage(),
            ]));

            return [
                'success' => false,
                'message' => 'SMS service error: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Clean phone number by removing spaces, dashes, and other characters
     *
     * @param  string  $phoneNumber
     * @return string
     */
    private function cleanPhoneNumber($phoneNumber)
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
     *
     * @param  string  $phoneNumber
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
                'message' => 'Error getting balance: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }
}
