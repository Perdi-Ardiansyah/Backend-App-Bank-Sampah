<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $judul }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .text-center { text-align: center; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .subtitle { font-size: 12px; color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 8px 10px; text-align: left; }
        th { background-color: #f4f4f4; font-weight: bold; }
    </style>
</head>
<body>

    <div class="text-center title">{{ $judul }}</div>
    <div class="text-center subtitle">Bank Sampah | Periode: {{ $dari }} s/d {{ $sampai }}</div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Tanggal</th>
                <th width="25%">Nama Nasabah</th>
                
                {{-- Kondisi Kolom Berdasarkan Tipe Laporan --}}
                @if($tipe === 'setoran')
                    <th>Kategori Sampah</th>
                    <th>Massa (Kg)</th>
                    <th>Poin Sirkulasi</th>
                @elseif($tipe === 'produk')
                    <th>Produk Sembako</th>
                    <th>Jumlah (Pcs)</th>
                    <th>Poin Keluar</th>
                @elseif($tipe === 'cash')
                    <th>Metode / Catatan</th>
                    <th>Nominal Pencairan</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($data as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $row->created_at->format('d M Y') }}</td>
                    <td>{{ $row->user->nama_lengkap ?? '-' }}</td>
                    
                    {{-- Kondisi Baris Data Berdasarkan Tipe Laporan --}}
                    @if($tipe === 'setoran')
                        <td>{{ $row->kategori->nama ?? '-' }}</td>
                        <td>{{ $row->berat_kg }} kg</td>
                        <td>+{{ number_format($row->poin_didapat, 0, ',', '.') }}</td>
                    @elseif($tipe === 'produk')
                        <td>{{ $row->produk->nama ?? '-' }}</td>
                        <td>{{ $row->jumlah }} pcs</td>
                        <td>-{{ number_format($row->total_poin, 0, ',', '.') }}</td>
                    @elseif($tipe === 'cash')
                        <td>{{ $row->catatan ?? 'Cash' }}</td>
                        <td>Rp {{ number_format($row->total_poin, 0, ',', '.') }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada transaksi pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>