from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def read(rel):
    return (ROOT / rel).read_text(encoding='utf-8')


def test_insert_button_uses_save_cancel_modal_contract():
    modal = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/modal.js')
    template = read('public/lib/editor/tiny/plugins/digieramedia/templates/modal.mustache')
    ui = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js')

    assert "import ModalSaveCancel from 'core/modal_save_cancel'" in modal
    assert 'extends ModalSaveCancel' in modal
    assert 'data-action="save"' in template
    assert 'data-action="cancel"' in template
    assert 'ModalEvents.save' in ui
    assert 'local_digieramedia_create_reference' in ui


def test_insert_errors_are_visible_inside_scrollable_body():
    template = read('public/lib/editor/tiny/plugins/digieramedia/templates/modal.mustache')
    scroll_start = template.index('class="tiny-digieramedia__scroll"')
    status_pos = template.index('data-region="status"')
    scroll_close = template.index('</div>\n            </section>', scroll_start)
    assert scroll_start < status_pos < scroll_close


if __name__ == '__main__':
    test_insert_button_uses_save_cancel_modal_contract()
    test_insert_errors_are_visible_inside_scrollable_body()
    print('INSERT_EVENT_CONTRACT=PASS')
