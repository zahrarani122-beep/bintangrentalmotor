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
            'countryCode' => '62',
            'followup'    => $followup,
        ]);

        return $response->json();
    }

    /**
     * Fungsi untuk mengirim file (PDF, gambar, dll) via URL
     */
    public function sendFile($target, $url, $caption = '')
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token,
        ])->asForm()->post($this->baseUrl, [
            'target'      => $target,
            'url'         => $url,
            'caption'     => $caption,
            'countryCode' => '62',
        ]);

        return $response->json();
    }
}