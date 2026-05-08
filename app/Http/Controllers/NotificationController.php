<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

// tambahan
use App\Services\FonnteService;

class NotificationController extends Controller
{
    protected $fonnteService;

    // Inject FonnteService ke constructor
    public function __construct(FonnteService $fonnteService)
    {
        $this->fonnteService = $fonnteService;
    }

    public function kirimNotifikasi()
    {
        $nomor_tujuan = '081321405677';
        $pesan = "Halo! Ini adalah pesan otomatis dari sistem Laravel.";

        // Panggil fungsi sendMessage dari service
        $proses = $this->fonnteService->sendMessage($nomor_tujuan, $pesan);

        if ($proses['status'] == true) {
            return response()->json(['message' => 'Pesan terkirim!']);
        } else {
            return response()->json(['message' => 'Gagal: ' . $proses['reason']], 500);
        }
    }

    // untuk mengirimkan pesan
    public function sendMessage($target, $message)
    {
        // Panggil fungsi sendMessage dari service
        $proses = $this->fonnteService->sendMessage($target, $message);

        if ($proses['status'] == true) {
            return response()->json(['message' => 'Pesan terkirim!']);
        } else {
            return response()->json(['message' => 'Gagal: ' . $proses['reason']], 500);
        }
    }
}
