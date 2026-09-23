<div id="invoice-show-modal" class="erp-modal invoice-iframe-modal" hidden>

    <div class="erp-ui-modal-backdrop" data-invoice-show-close></div>

    <div class="erp-ui-modal-panel invoice-iframe-modal-panel">

        <div class="erp-modal-header">

            <h3 id="invoice-show-modal-title">نمایش فاکتور</h3>

            <button type="button" class="erp-modal-close" data-invoice-show-close aria-label="بستن">×</button>

        </div>

        <div class="erp-modal-body invoice-iframe-modal-body">

            <iframe id="invoice-show-iframe" title="نمایش فاکتور" class="invoice-iframe"></iframe>

        </div>

    </div>

</div>



<div id="invoice-edit-modal" class="erp-modal invoice-iframe-modal" hidden>

    <div class="erp-ui-modal-backdrop" data-invoice-edit-close></div>

    <div class="erp-ui-modal-panel invoice-iframe-modal-panel">

        <div class="erp-modal-header">

            <h3 id="invoice-edit-modal-title">ویرایش</h3>

            <button type="button" class="erp-modal-close" data-invoice-edit-close aria-label="بستن">×</button>

        </div>

        <div class="erp-modal-body invoice-iframe-modal-body">

            <iframe id="invoice-edit-iframe" title="ویرایش فاکتور" class="invoice-iframe"></iframe>

        </div>

    </div>

</div>



<script>

(() => {

    const showModal = document.getElementById('invoice-show-modal');

    const showIframe = document.getElementById('invoice-show-iframe');

    const showTitle = document.getElementById('invoice-show-modal-title');

    const editModal = document.getElementById('invoice-edit-modal');

    const editIframe = document.getElementById('invoice-edit-iframe');

    const editTitle = document.getElementById('invoice-edit-modal-title');

    const invoiceUrlBase = @json(url('invoices')) + '/';

    const crmInvoiceUrlBase = @json(url('crm/invoices')) + '/';




    function refreshLivewireHost() {

        if (typeof Livewire === 'undefined') {

            return;

        }



        const host = showModal?.closest('[wire\\:id]') || editModal?.closest('[wire\\:id]');



        if (!host) {

            if (Livewire.first()) {

                Livewire.first().$refresh();

            }



            return;

        }



        const component = Livewire.find(host.getAttribute('wire:id'));



        if (component) {

            component.$refresh();

        }

    }



    function openInvoiceShow(invoiceId, options = {}) {

        if (!showModal || !showIframe) {

            return;

        }



        closeInvoiceEdit();

        showIframe.src = options.crm

            ? `${crmInvoiceUrlBase}${invoiceId}`

            : `${invoiceUrlBase}${invoiceId}?embedded=1`;



        if (showTitle) {

            showTitle.textContent = options.title || 'نمایش فاکتور';

        }



        showModal.hidden = false;

        document.body.classList.add('erp-modal-open');

    }



    function closeInvoiceShow() {

        if (!showModal || !showIframe) {

            return;

        }



        showModal.hidden = true;

        showIframe.src = 'about:blank';



        if (!editModal || editModal.hidden) {

            document.body.classList.remove('erp-modal-open');

        }

    }



    function openInvoiceEdit(invoiceId, options = {}) {

        if (!editModal || !editIframe) {

            return;

        }



        closeInvoiceShow();

        editIframe.src = options.crm

            ? `${crmInvoiceUrlBase}${invoiceId}/edit`

            : `${invoiceUrlBase}${invoiceId}/edit?embedded=1`;



        if (editTitle) {

            editTitle.textContent = options.title || 'ویرایش';

        }



        editModal.hidden = false;

        document.body.classList.add('erp-modal-open');

    }



    function closeInvoiceEdit() {

        if (!editModal || !editIframe) {

            return;

        }



        editModal.hidden = true;

        editIframe.src = 'about:blank';



        if (!showModal || showModal.hidden) {

            document.body.classList.remove('erp-modal-open');

        }

    }



    window.openInvoiceShow = openInvoiceShow;

    window.closeInvoiceShow = closeInvoiceShow;

    window.openInvoiceEdit = openInvoiceEdit;

    window.closeInvoiceEdit = closeInvoiceEdit;



    document.addEventListener('click', (event) => {

        const showTrigger = event.target.closest('[data-invoice-show]');

        if (showTrigger) {

            event.preventDefault();

            openInvoiceShow(showTrigger.dataset.invoiceShow, {

                crm: showTrigger.dataset.invoiceShowCrm === '1',

                title: showTrigger.dataset.invoiceShowTitle || 'نمایش فاکتور',

            });

            return;

        }



        if (event.target.closest('[data-invoice-show-close]')) {

            event.preventDefault();

            closeInvoiceShow();

            return;

        }



        if (event.target.closest('[data-invoice-edit-close]')) {

            event.preventDefault();

            closeInvoiceEdit();

        }

    });



    window.addEventListener('message', (event) => {

        if (event.origin !== window.location.origin) {

            return;

        }



        const type = event.data?.type;



        if (type === 'invoice-show-cancel') {

            closeInvoiceShow();

            return;

        }



        if (type === 'invoice-edit-cancel') {

            closeInvoiceEdit();

            return;

        }



        if (type === 'invoice-open-edit') {

            if (event.data.invoiceId) {

                const isProforma = event.data.documentType === 'proforma';

                openInvoiceEdit(event.data.invoiceId, {

                    crm: Boolean(event.data.crmContext),

                    title: isProforma ? 'ویرایش پیش‌فاکتور' : 'ویرایش فاکتور',

                });

            }

            return;

        }



        if (type === 'invoice-saved') {

            closeInvoiceEdit();

            refreshLivewireHost();



            if (event.data.message && typeof window.showErpToast === 'function') {

                window.showErpToast(event.data.message);

            }

            return;

        }



        if (type === 'invoice-validation-error') {

            if (event.data.message && typeof window.showErpToast === 'function') {

                window.showErpToast(event.data.message, 'danger');

            }

            return;

        }



        if (type === 'invoice-updated') {

            if (showIframe && showModal && !showModal.hidden) {

                const reloadId = event.data.reloadShowId || event.data.invoice?.id;



                if (reloadId) {

                    showIframe.src = `${crmInvoiceUrlBase}${reloadId}`;

                }

            }



            refreshLivewireHost();



            if (event.data.message && typeof window.showErpToast === 'function') {

                window.showErpToast(event.data.message);

            }

        }

    });



    document.addEventListener('keydown', (event) => {

        if (event.key !== 'Escape') {

            return;

        }



        if (editModal && !editModal.hidden) {

            closeInvoiceEdit();

            return;

        }



        if (showModal && !showModal.hidden) {

            closeInvoiceShow();

        }

    });

})();

</script>

