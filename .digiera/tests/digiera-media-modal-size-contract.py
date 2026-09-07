from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def read(rel):
    return (ROOT / rel).read_text(encoding='utf-8')


def test_modal_dialog_uses_moodle_modal_api_for_approved_size():
    modal = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/modal.js')
    css = read('public/lib/editor/tiny/plugins/digieramedia/styles.css')
    ui = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js')

    assert "this.getModal().addClass('tiny-digieramedia-dialog')" in modal
    assert "'--bs-modal-width': '1280px'" in modal
    assert "'width': '92vw'" in modal
    assert "'max-width': '1280px'" in modal
    assert "'height': '85vh'" in modal
    assert 'root.querySelector(\'.modal-dialog\')' not in ui
    assert '.tiny-digieramedia-dialog .modal-content' in css
    assert 'height: 100%' in css


if __name__ == '__main__':
    test_modal_dialog_uses_moodle_modal_api_for_approved_size()
    print('MODAL_SIZE_CONTRACT=PASS')
