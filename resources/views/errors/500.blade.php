<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطای داخلی سرور</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <style>
        body { margin: 0; font-family: Vazirmatn, sans-serif; background: #fff1f2; color: #7f1d1d; min-height: 100vh; display: grid; place-items: center; }
        .card { width: min(640px, calc(100vw - 2rem)); border: 1px solid #fecdd3; border-radius: 20px; background: #fff; padding: 2rem; box-shadow: 0 20px 60px rgba(185, 28, 28, .08); }
        .badge { display: inline-flex; border-radius: 999px; background: #fef2f2; border: 1px solid #fecaca; padding: .35rem .8rem; font-size: .85rem; font-weight: 800; color: #b91c1c; }
        h1 { margin: 1rem 0 .5rem; font-size: 1.7rem; font-weight: 900; }
        p { margin: 0; line-height: 1.9; color: #991b1b; }
        a { display: inline-block; margin-top: 1.25rem; padding: .75rem 1.1rem; border-radius: 12px; background: #b91c1c; color: #fff; text-decoration: none; font-weight: 800; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">خطا 500</div>
        <h1>مشکلی در پردازش درخواست رخ داده است.</h1>
        <p>لطفا کمی بعد دوباره تلاش کنید یا با پشتیبانی سیستم تماس بگیرید.</p>
        <a href="{{ route('dashboard') }}">بازگشت به داشبورد</a>
    </div>
</body>
</html>
