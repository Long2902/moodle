import Modal from 'core/modal';

export default class DigieraMediaModal extends Modal {
    static TYPE = 'tiny_digieramedia/modal';
    static TEMPLATE = 'tiny_digieramedia/modal';

    configure(config) {
        config.large = true;
        config.show = true;
        config.removeOnClose = true;
        super.configure(config);

        this.getModal().addClass('tiny-digieramedia-dialog');
        this.getModal().css({
            '--bs-modal-width': '1280px',
            'width': '92vw',
            'max-width': '1280px',
            'height': '85vh',
        });
    }

    registerEventListeners() {
        super.registerEventListeners();
        this.registerCloseOnCancel();
    }
}

DigieraMediaModal.registerModalType();
