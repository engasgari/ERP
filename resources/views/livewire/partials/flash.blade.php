@if(session('success'))
    <x-erp.ui.alert tone="success" :message="session('success')" />
@endif

@if(session('error'))
    <x-erp.ui.alert
        tone="danger"
        title="خطا"
        :message="session('error')"
        :details="session('error_details')"
    />
@endif
