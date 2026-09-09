from pathlib import Path
root=Path(__file__).resolve().parents[1]
pkg=(root/'client/package.json').read_text()
editor=(root/'client/src/editor.js').read_text()
ribbon=(root/'client/src/ribbon.js').read_text()
exts=(root/'client/src/tiptap_extensions.js')
libbridge=(root.parent/'worksheetlibrary/amd/src/native_editor.js').read_text()
student=(root.parents[1]/'mod/worksheetgrader/amd/src/native_attempt.js').read_text()
assert '"@tiptap/core"' in pkg
assert '"@tiptap/starter-kit"' in pkg
assert '"@tiptap/extension-text-align"' in pkg
assert exts.exists(), 'missing tiptap_extensions.js'
assert "from '@tiptap/core'" in editor
assert 'StarterKit' in editor
assert 'onUpdate' in editor
for tab in ['File','Home','Insert','Layout','Review','Math','View']:
    assert repr(tab) in ribbon or f"'{tab}'" in ribbon
assert '.chain().focus()' in ribbon
assert 'onUpdate:' in libbridge
assert 'setProps' not in libbridge
assert 'onUpdate:' in student
assert 'setProps' not in student
print('TIPTAP_HYBRID_STATIC_CONTRACT=PASS')
