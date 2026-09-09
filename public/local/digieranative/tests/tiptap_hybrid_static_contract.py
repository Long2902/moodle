from pathlib import Path
root=Path(__file__).resolve().parents[1]
pkg=(root/'client/package.json').read_text()
editor=(root/'client/src/editor.js').read_text()
ribbon=(root/'client/src/ribbon.js').read_text()
toolbar=(root/'client/src/ui/toolbar.js').read_text()
exts=(root/'client/src/tiptap_extensions.js')
preview=(root/'client/src/preview_renderer.js').read_text()
libbridge=(root.parent/'worksheetlibrary/amd/src/native_editor.js').read_text()
libbrowser=(root.parent/'worksheetlibrary/amd/src/browser.js').read_text()
student=(root.parents[1]/'mod/worksheetgrader/amd/src/native_attempt.js').read_text()
assert '"@tiptap/core"' in pkg
assert '"@tiptap/react"' in pkg
assert '"@tiptap/starter-kit"' in pkg
assert '"@tiptap/extension-text-align"' in pkg
assert '"react"' in pkg and '"react-dom"' in pkg
assert exts.exists(), 'missing tiptap_extensions.js'
assert "from '@tiptap/core'" in editor
assert "from '@tiptap/react'" in editor
assert 'OfficialEditor' in editor
assert 'createRibbon' not in editor
assert 'StarterKit' in editor
assert 'onUpdate' in editor
for dead in ['layout-info','review-info','view-info']:
    assert dead not in ribbon
    assert dead not in toolbar
for command in ['bold','italic','underline','strike','bullet-list','ordered-list','align-left','align-center','align-right','align-justify','link','question','short-answer','long-answer','answer-table','image','math','save']:
    assert command in toolbar
assert '.chain().focus()' in toolbar
assert 'generateHTML' in preview
assert 'onUpdate:' in libbridge
assert 'wslib:native-document' in libbridge
assert 'NativeEditor.renderPreview' in libbrowser
assert 'canvas.innerHTML' not in libbrowser
assert 'setProps' not in libbridge
assert 'onUpdate:' in student
assert 'setProps' not in student
print('TIPTAP_OFFICIAL_UI_STATIC_CONTRACT=PASS')
