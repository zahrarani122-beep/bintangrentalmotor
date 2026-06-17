<?php

namespace App\Services;

use App\Models\Motor;
use App\Models\MotorInsight;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeminiMotorInsightService
{
    public function analyze(): MotorInsight
    {
        if (blank(config('services.gemini.api_key'))) {
            throw new \RuntimeException('GEMINI_API_KEY belum diatur di file .env.');
        }

        $motors = Motor::query()
            ->select([
                'nama_motor',
                'jenis_motor',
                'merek_motor',
                'plat_nomor',
                'status',
                'harga_sewa_perhari',
            ])
            ->orderBy('nama_motor')
            ->get();

        $response = Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($this->endpoint(), [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $this->buildPrompt($motors->toArray()),
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'response_schema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'summary' => ['type' => 'STRING'],
                            'top_motor' => ['type' => 'STRING'],
                            'recommendation' => ['type' => 'STRING'],
                        ],
                        'required' => [
                            'summary',
                            'top_motor',
                            'recommendation',
                        ],
                    ],
                ],
            ]);

        if ($response->status() === 429) {
            throw new \RuntimeException(
                'Kuota Gemini API sudah habis atau terkena rate limit. Coba tunggu beberapa saat, gunakan API key lain, atau cek plan/billing di Google AI Studio.'
            );
        }

        if ($response->failed()) {
            $message = $response->json('error.message') ?? Str::limit($response->body(), 160);

            throw new \RuntimeException('Gemini API gagal: ' . $message);
        }

        $rawResponse = $response->json();
        $text = data_get($rawResponse, 'candidates.0.content.parts.0.text');
        $result = json_decode((string) $text, true);

        if (! is_array($result)) {
            $result = json_decode($this->cleanJsonText((string) $text), true);
        }

        if (! is_array($result)) {
            throw new \RuntimeException('Gemini tidak mengembalikan JSON yang valid.');
        }

        return MotorInsight::create([
            'summary' => $result['summary'] ?? 'Belum ada ringkasan dari AI.',
            'top_motor' => $result['top_motor'] ?? null,
            'recommendation' => $result['recommendation'] ?? 'Belum ada rekomendasi dari AI.',
            'raw_response' => $rawResponse,
        ]);
    }

    private function endpoint(): string
    {
        $baseUrl = rtrim(config('services.gemini.base_url'), '/');
        $model = config('services.gemini.model');
        $apiKey = config('services.gemini.api_key');

        return "{$baseUrl}/{$model}:generateContent?key={$apiKey}";
    }

    private function buildPrompt(array $motors): string
    {
        $dataMotor = json_encode($motors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Anda adalah asisten analisis untuk aplikasi rental motor.

Analisis data master motor berikut dan berikan insight sederhana.
Fokus hanya pada data master motor, seperti nama motor, jenis, merek, status, dan harga sewa per hari.
Jangan memakai data penyewaan, pendapatan, pelanggan, atau analisis bisnis lanjutan.

Data motor:
{$dataMotor}

Kembalikan jawaban hanya dalam JSON valid dengan format berikut:
{
  "summary": "ringkasan singkat kondisi data motor",
  "top_motor": "nama motor yang paling direkomendasikan",
  "recommendation": "rekomendasi singkat untuk pengelolaan motor"
}
PROMPT;
    }

    private function cleanJsonText(string $text): string
    {
        return Str::of($text)
            ->replace('```json', '')
            ->replace('```', '')
            ->trim()
            ->toString();
    }
}
