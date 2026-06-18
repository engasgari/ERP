<script>
    (() => {
        if (window.erpModalControllerBound) {
            return;
        }

        window.erpModalControllerBound = true;

        const modalSelector = '.erp-ui-modal-backdrop, .erp-modal';

        function owningModal(element) {
            return element?.closest('.erp-modal') || element?.closest('.erp-ui-modal-backdrop');
        }

        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) {
                return;
            }

            modal.hidden = false;
            modal.classList.add('is-open');
            document.body.classList.add('erp-modal-open');
        }

        function closeModal(modal) {
            if (!modal) {
                return;
            }

            modal.hidden = true;
            modal.classList.remove('is-open');

            if (!document.querySelector(`${modalSelector}:not([hidden])`)) {
                document.body.classList.remove('erp-modal-open');
            }
        }

        function closeAllModals() {
            document.querySelectorAll(`${modalSelector}:not([hidden])`).forEach(closeModal);
        }

        document.addEventListener('click', (event) => {
            const opener = event.target.closest('[data-erp-modal-open]');
            const closer = event.target.closest('[data-erp-modal-close]');

            if (opener) {
                event.preventDefault();
                const nextModalId = opener.dataset.erpModalOpen;
                const currentModal = owningModal(opener);

                if (closer || currentModal) {
                    closeModal(currentModal);
                }

                window.requestAnimationFrame(() => openModal(nextModalId));
                return;
            }

            if (closer) {
                event.preventDefault();
                closeModal(owningModal(closer));
                return;
            }

            const modal = owningModal(event.target);
            if (modal && event.target === modal) {
                closeModal(modal);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllModals();
            }
        });

        document.addEventListener('livewire:navigated', closeAllModals);
    })();
</script>
