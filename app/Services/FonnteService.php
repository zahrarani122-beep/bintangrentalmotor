<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FonnteService
{
    protected $token;
    protected $baseUrl = 'https://api.fonnte.com/send';

    public function __construct()
    {
        $this->token = env('FONNTE_TOKEN');
    }

    /**
     * Fungsi untuk mengirim pesan WhatsApp
     */
    public function sendMessage($target, $message, $followup = 0)
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token,
        ])->asForm()->post($this->baseUrl, [
            'target'      => $target,
            'message'     => $message,
            'countryCode' => '62', // Default Indonesia
            'followup'    => $followup,
        ]);

        return $response->json();
    }
}