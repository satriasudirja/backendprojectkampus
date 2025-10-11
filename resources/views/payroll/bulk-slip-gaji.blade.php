<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji Bulk - {{ $periode->nama_periode ?? 'Multiple Periods' }}</title>
    <style>
        @page {
            margin: 20mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .slip-container {
            margin-bottom: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #1e40af;
        }
        
        .header p {
            margin: 3px 0 0 0;
            color: #6b7280;
            font-size: 10px;
        }
        
        .info-pegawai {
            margin-bottom: 15px;
            background-color: #f9fafb;
            padding: 10px;
            border-radius: 3px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 5px;
            font-size: 10px;
        }
        
        .info-col {
            display: flex;
            flex-direction: column; /* Mengubah orientasi menjadi kolom */
            gap: 5px; /* Cara modern untuk memberi jarak antar item di dalam kolom */
            font-size: 10px;
        }
        
        .info-label {
            width: 100px;
            font-weight: bold;
        }
        
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin: 15px 0 8px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section-title.pendapatan {
            color: #059669;
        }
        
        .section-title.potongan {
            color: #dc2626;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        
        table td {
            padding: 5px 0;
            font-size: 10px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        table td:first-child {
            width: 65%;
        }
        
        table td:last-child {
            text-align: right;
            font-weight: 500;
        }
        
        .subtotal {
            font-weight: bold;
            background-color: #f9fafb;
            padding: 8px 0 !important;
            border-top: 2px solid #d1d5db !important;
            font-size: 11px;
        }
        
        .gaji-bersih {
            background-color: #dbeafe;
            border: 2px solid #2563eb;
            border-radius: 3px;
            padding: 12px;
            text-align: center;
            margin-top: 15px;
        }
        
        .gaji-bersih .label {
            font-size: 11px;
            font-weight: bold;
            color: #1e40af;
        }
        
        .gaji-bersih .amount {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            margin-top: 5px;
        }
        
        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 9px;
        }
    </style>
</head>
<body>
    @foreach($slips as $index => $slip)
    <div class="slip-container {{ $index < count($slips) - 1 ? 'page-break' : '' }}">
        <div class="header">
            <h1>SLIP GAJI PEGAWAI</h1>
            <p>{{ $slip->periode->nama_periode }}</p>
        </div>
        
        <div class="info-pegawai">
            <div class="info-col">
                <div class="info-label">NIP :</div>
                <div> {{ $slip->pegawai->nip }}</div>
            </div>
            <div class="info-col">
                <div class="info-label">Nama :</div>
                <div> {{ $slip->pegawai->nama }}</div>
            </div>
        </div>
        
        <!-- PENDAPATAN -->
        <div class="section-title pendapatan">PENDAPATAN</div>
        <table>
            @foreach($slip->komponenPendapatan as $komponen)
            <tr>
                <td>{{ $komponen->deskripsi }}</td>
                <td>Rp {{ number_format($komponen->nominal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td>TOTAL PENDAPATAN</td>
                <td style="color: #059669;">Rp {{ number_format($slip->total_pendapatan, 0, ',', '.') }}</td>
            </tr>
        </table>
        
        <!-- POTONGAN -->
        <div class="section-title potongan">POTONGAN</div>
        <table>
            @foreach($slip->komponenPotongan as $komponen)
            <tr>
                <td>{{ $komponen->deskripsi }}</td>
                <td>Rp {{ number_format($komponen->nominal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td>TOTAL POTONGAN</td>
                <td style="color: #dc2626;">Rp {{ number_format($slip->total_potongan, 0, ',', '.') }}</td>
            </tr>
        </table>
        
        <!-- GAJI BERSIH -->
        <div class="gaji-bersih">
            <div class="label">GAJI BERSIH</div>
            <div class="amount">Rp {{ number_format($slip->gaji_bersih, 0, ',', '.') }}</div>
        </div>
        
        <div class="footer">
            <p>Dicetak pada: {{ now()->format('d F Y, H:i') }} WIB</p>
        </div>
    </div>
    @endforeach
</body>
</html>