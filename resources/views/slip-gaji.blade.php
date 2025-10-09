<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji - {{ $slip->pegawai->nama }}</title>
    <style>
        @page {
            margin: 20mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1e40af;
        }
        
        .header p {
            margin: 5px 0 0 0;
            color: #6b7280;
        }
        
        .info-pegawai {
            margin-bottom: 25px;
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 5px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            width: 150px;
            font-weight: bold;
            color: #4b5563;
        }
        
        .info-value {
            flex: 1;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e5e7eb;
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
            margin-bottom: 10px;
        }
        
        table td {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        table td:first-child {
            width: 70%;
        }
        
        table td:last-child {
            text-align: right;
            font-weight: 500;
        }
        
        .subtotal {
            font-weight: bold;
            background-color: #f9fafb;
            padding: 10px 0 !important;
            border-top: 2px solid #d1d5db !important;
        }
        
        .total-pendapatan {
            color: #059669;
        }
        
        .total-potongan {
            color: #dc2626;
        }
        
        .gaji-bersih {
            background-color: #dbeafe;
            border: 2px solid #2563eb;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            margin-top: 25px;
        }
        
        .gaji-bersih .label {
            font-size: 16px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 10px;
        }
        
        .gaji-bersih .amount {
            font-size: 28px;
            font-weight: bold;
            color: #1e40af;
        }
        
        .footer {
            margin-top: 40px;
            text-align: right;
        }
        
        .signature {
            margin-top: 60px;
            display: inline-block;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            padding-top: 5px;
            margin-top: 5px;
            min-width: 200px;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(0, 0, 0, 0.03);
            z-index: -1;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="watermark">SLIP GAJI</div>
    
    <div class="header">
        <h1>SLIP GAJI PEGAWAI</h1>
        <p>{{ $slip->periode->nama_periode }}</p>
    </div>
    
    <div class="info-pegawai">
        <div class="info-row">
            <div class="info-label">NIP</div>
            <div class="info-value">: {{ $slip->pegawai->nip }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Nama</div>
            <div class="info-value">: {{ $slip->pegawai->nama }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tanggal Cetak</div>
            <div class="info-value">: {{ now()->format('d F Y, H:i') }}</div>
        </div>
    </div>
    
    <!-- PENDAPATAN -->
    <div class="section">
        <div class="section-title pendapatan">PENDAPATAN</div>
        <table>
            @foreach($slip->komponenPendapatan as $komponen)
            <tr>
                <td>{{ $komponen->deskripsi }}</td>
                <td>Rp {{ number_format($komponen->nominal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal total-pendapatan">
                <td>TOTAL PENDAPATAN</td>
                <td>Rp {{ number_format($slip->total_pendapatan, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>
    
    <!-- POTONGAN -->
    <div class="section">
        <div class="section-title potongan">POTONGAN</div>
        <table>
            @foreach($slip->komponenPotongan as $komponen)
            <tr>
                <td>{{ $komponen->deskripsi }}</td>
                <td>Rp {{ number_format($komponen->nominal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal total-potongan">
                <td>TOTAL POTONGAN</td>
                <td>Rp {{ number_format($slip->total_potongan, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>
    
    <!-- GAJI BERSIH -->
    <div class="gaji-bersih">
        <div class="label">GAJI BERSIH YANG DITERIMA</div>
        <div class="amount">Rp {{ number_format($slip->gaji_bersih, 0, ',', '.') }}</div>
    </div>
    
    <div class="footer">
        <p>Mengetahui,</p>
        <div class="signature">
            <div class="signature-line">
                ( ............................ )
            </div>
            <p style="margin-top: 5px; font-size: 11px;">Bagian Keuangan</p>
        </div>
    </div>
</body>
</html>