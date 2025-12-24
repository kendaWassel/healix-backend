<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class UltraMsgService
{
    protected $token;
    protected $instanceId;

    public function __construct()
    {
        $this->token = env('ULTRAMSG_TOKEN');
        $this->instanceId = env('ULTRAMSG_INSTANCE');
    }

    /**
     * Format phone number to UltraMsg format (international format without +)
     * Example: +1234567890 -> 1234567890
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Remove leading + if present
        $phone = ltrim($phone, '+');
        
        return $phone;
    }

    /**
     * Check if UltraMsg is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->instanceId);
    }

    /**
     * Send WhatsApp message via UltraMsg API
     * 
     * @param string $to Phone number (will be formatted automatically)
     * @param string $message Message content
     * @return array Returns ['success' => bool, 'message' => string, 'response' => mixed]
     */
    public function sendWhatsAppMessage(string $to, string $message): array
    {
        // Check if configured
        if (!$this->isConfigured()) {
            Log::warning('UltraMsg not configured. Missing ULTRAMSG_TOKEN or ULTRAMSG_INSTANCE');
            return [
                'success' => false,
                'message' => 'UltraMsg service not configured',
                'response' => null
            ];
        }

        // Format phone number
        $formattedPhone = $this->formatPhoneNumber($to);
        
        if (empty($formattedPhone)) {
            Log::error("UltraMsg: Invalid phone number format: $to");
            return [
                'success' => false,
                'message' => 'Invalid phone number format',
                'response' => null
            ];
        }

        $params = [
            'token' => $this->token,
            'to' => $formattedPhone,
            'body' => $message,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.ultramsg.com/' . $this->instanceId . '/messages/chat',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'content-type: application/x-www-form-urlencoded'
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error("UltraMsg cURL Error: $err", [
                'phone' => $formattedPhone,
                'message_length' => strlen($message)
            ]);
            return [
                'success' => false,
                'message' => "cURL error: $err",
                'response' => null
            ];
        }

        // Parse response
        $responseData = json_decode($response, true);
        
        Log::info("UltraMsg Response", [
            'http_code' => $httpCode,
            'response' => $responseData,
            'phone' => $formattedPhone
        ]);

        // Check if message was sent successfully
        // UltraMsg API typically returns success in response or HTTP 200
        $success = ($httpCode === 200 && $responseData !== null);
        
        if (isset($responseData['sent'])) {
            $success = $responseData['sent'] === true || $responseData['sent'] === 'true';
        }

        return [
            'success' => $success,
            'message' => $success ? 'Message sent successfully' : 'Failed to send message',
            'response' => $responseData,
            'http_code' => $httpCode
        ];
    }
}