<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت فایل‌های اکسل</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">📊 مدیریت وارد کردن داده از اکسل</h4>
                </div>
                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success">
                            <strong>✅ موفقیت:</strong> {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            <strong>❌ خطا:</strong>
                            @if(is_array(session('error')))
                                <ul class="mb-0">
                                    @foreach(session('error') as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @else
                                {{ session('error') }}
                            @endif
                        </div>
                    @endif

                    <div class="mb-4">
                        <h5>📥 وارد کردن داده از اکسل</h5>
                        <form action="{{ route('import') }}" method="POST" enctype="multipart/form-data" class="mt-3">
                            @csrf
                            <div class="mb-3">
                                <label for="file" class="form-label">انتخاب فایل اکسل</label>
                                <input type="file" class="form-control" id="file" name="file"
                                       accept=".xlsx,.xls,.csv" required>
                                <div class="form-text">
                                    فرمت‌های مجاز: Excel (.xlsx, .xls) یا CSV - حداکثر سایز: 10MB
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                📤 آپلود و وارد کردن داده‌ها
                            </button>
                        </form>
                    </div>

                    <hr>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5>📤 خروجی گرفتن از داده‌ها</h5>
                            <a href="{{ route('export') }}" class="btn btn-success w-100 py-2">
                                📄 دانلود فایل اکسل
                            </a>
                        </div>
                        <div class="col-md-6">
                            <h5>📋 فایل نمونه</h5>
                            <a href="{{ route('template') }}" class="btn btn-outline-primary w-100 py-2">
                                📝 دانلود فایل نمونه
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded">
                        <h6>💡 راهنما:</h6>
                        <ul class="mb-0 small">
                            <li>فایل اکسل باید دارای سطر عنوان (header) باشد</li>
                            <li>ستون‌های مورد نیاز: name, email</li>
                            <li>ستون password اختیاری است (در صورت عدم وجود، مقدار پیش‌فرض تنظیم می‌شود)</li>
                            <li>از صحت فرمت ایمیل‌ها مطمئن شوید</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
