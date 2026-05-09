<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected $token;

    protected $baseUrl = 'https://api.fonnte.com/send';

    public function __construct()
    {
        $this->token = env('FONNTE_TOKEN');
    }

    public function sendMessage($target, $message)
    {
        try {
            if (empty($this->token)) {
                return [
                    'status' => false,
                    'reason' => 'Token Fonnte belum diisi di file .env',
                ];
            }

            if (empty($target)) {
                return [
                    'status' => false,
                    'reason' => 'Nomor tujuan kosong',
                ];
            }

            if (empty($message)) {
                return [
                    'status' => false,
                    'reason' => 'Isi pesan kosong',
                ];
            }

            $target = $this->formatNomorWa($target);

            $response = Http::withHeaders([
                'Authorization' => trim($this->token),
            ])
                ->asForm()
                ->timeout(30)
                ->post($this->baseUrl, [
                    'target' => $target,
                    'message' => $message,
                    'countryCode' => '62',
                ]);

            $result = $response->json();

            if (!$response->successful()) {
                Log::error('Fonnte HTTP Error', [
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'status' => false,
                    'reason' => $result['reason'] ?? 'Gagal menghubungi server Fonnte',
                    'response' => $result,
                ];
            }

            if (($result['status'] ?? false) == false) {
                Log::error('Fonnte API Error', [
                    'response' => $result,
                ]);

                return [
                    'status' => false,
                    'reason' => $result['reason'] ?? 'Pesan gagal dikirim oleh Fonnte',
                    'response' => $result,
                ];
            }

            return [
                'status' => true,
                'reason' => $result['reason'] ?? null,
                'response' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('Fonnte Exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'reason' => $e->getMessage(),
            ];
        }
    }

    private function formatNomorWa($nomor)
    {
        $nomor = preg_replace('/[^0-9]/', '', (string) $nomor);

        if (substr($nomor, 0, 1) === '0') {
            $nomor = '62' . substr($nomor, 1);
        }

        return $nomor;
    }
}