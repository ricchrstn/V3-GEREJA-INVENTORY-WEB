<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengajuan extends Model
{
    use HasFactory;

    protected $table = 'pengajuan';
    protected $fillable = [
        'kode_pengajuan',
        'nama_barang',
        'spesifikasi',
        'jumlah',
        'satuan',
        'alasan',
        'kebutuhan',
        'user_id',
        'status',
        'keterangan',
        'file_pengajuan',
        'urgensi', // K1 - Tingkat Urgensi Barang (Benefit)
        'ketersediaan_stok', // K2 - Ketersediaan Stok Barang (Cost)
        'ketersediaan_dana', // K3 - Ketersediaan Dana Pengadaan (Benefit)
    ];

    protected $casts = [
        'kebutuhan' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function analisisTopsis()
    {
        return $this->hasOne(AnalisisTopsis::class);
    }

    public function nilaiPengadaanKriterias()
    {
        return $this->hasMany(NilaiPengadaanKriteria::class);
    }

    // Generate kode pengajuan otomatis
    public static function generateKode()
    {
        $prefix = 'PNG';
        $date = now()->format('Ymd');
        $last = self::whereDate('created_at', now())->count();
        $number = str_pad($last + 1, 3, '0', STR_PAD_LEFT);
        return $prefix . $date . $number;
    }

    /**
     * Get the kategori that owns the pengajuan.
     */
    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    /**
     * Otomatis menghitung nilai ketersediaan dana (K3) berdasarkan saldo kas
     */
    public function hitungKetersediaanDanaOtomatis()
    {
        $saldoKas = \App\Models\Kas::getSaldo();

        if ($saldoKas > 8000000) {
            return 10; // Sangat tinggi (> Rp 8.000.000)
        } elseif ($saldoKas >= 6000000) {
            return 8;  // Tinggi (Rp 6.000.000 - Rp 8.000.000)
        } elseif ($saldoKas >= 4000000) {
            return 6;  // Sedang (Rp 4.000.000 - Rp 5.999.999)
        } elseif ($saldoKas >= 2000000) {
            return 4;  // Rendah (Rp 2.000.000 - Rp 3.999.999)
        } else {
            return 2;  // Sangat rendah (< Rp 2.000.000)
        }
    }

    /**
     * Update ketersediaan dana secara otomatis
     */
    public function updateKetersediaanDanaOtomatis()
    {
        $this->ketersediaan_dana = $this->hitungKetersediaanDanaOtomatis();
        $this->save();

        return $this->ketersediaan_dana;
    }

    /**
     * Boot method untuk observer
     */
    protected static function boot()
    {
        parent::boot();

        // Update ketersediaan dana otomatis saat pengajuan dibuat
        static::created(function ($pengajuan) {
            $pengajuan->updateKetersediaanDanaOtomatis();
        });

        // Update ketersediaan dana otomatis saat status berubah menjadi pending
        static::updated(function ($pengajuan) {
            if ($pengajuan->wasChanged('status') && $pengajuan->status === 'pending') {
                $pengajuan->updateKetersediaanDanaOtomatis();
            }
        });
    }
}
