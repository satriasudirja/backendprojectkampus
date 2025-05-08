<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAPTCHA Error</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            text-align: center;
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 340px;
        }
        h2 {
            color: #e74c3c;
            margin-top: 0;
            margin-bottom: 15px;
        }
        p {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .error-icon {
            font-size: 48px;
            color: #e74c3c;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        <h2>Terjadi Kesalahan</h2>
        <p>{{ $message ?? 'Terjadi kesalahan saat menampilkan CAPTCHA. Silakan coba lagi.' }}</p>
        <a href="{{ route('captcha.slide-captcha') }}" class="btn">Coba Lagi</a>
    </div>
</body>
</html>