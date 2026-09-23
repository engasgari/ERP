@once('erp-session-flashes')
    @if(session('success'))
        <x-erp.ui.flash-toast tone="success" :message="session('success')" />
    @endif

    @if(session('error'))
        <x-erp.ui.flash-toast
            tone="danger"
            :message="session('error')"
            :details="session('error_details', [])"
        />
    @endif

    @if(session('warning'))
        <x-erp.ui.flash-toast tone="warning" :message="session('warning')" />
    @endif

    @if(session('info'))
        <x-erp.ui.flash-toast tone="info" :message="session('info')" />
    @endif
@endonce
