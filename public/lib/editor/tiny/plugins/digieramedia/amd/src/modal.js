import Modal from 'core/modal';

export default class DigieraMediaModal extends Modal {
    static TYPE = 'tiny_digieramedia/modal';
    static TEMPLATE = 'tiny_digieramedia/modal';

    configure(config) {
        config.large = true;
        config.show = true;
        config.removeOnClose = true;
        super.configure(config);
    }

    registerEventListeners() {
        super.registerEventListeners();
        this.registerCloseOnCancel();
    }
}

DigieraMediaModal.registerModalType();
