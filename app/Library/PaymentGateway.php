<?php

namespace App\Library;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGateway

{

    static function createPayment(array $data)
    {
        $baseUrl = config('payment_gateway.url');
        $path = '/api/payments';
        $endpoint = $baseUrl . $path;

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'x-api-key' => config('payment_gateway.api_key'),
            ])
            ->post($endpoint, $data);

        return $response;
    }
}
