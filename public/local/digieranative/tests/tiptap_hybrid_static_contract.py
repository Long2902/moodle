from pathlib import Path
root=Path(__file__).resolve().parents[1]
pkg=(root/'client/package.json').read_text()
editor=(root/'client/src/editor.js').read_text()
ribbon=(root/'client/src/ribbon.js').read_text()
legacytoolbar=(root/'client/src/ui/toolbar.js').read_text()
cambridge=(root/'client/src/ui/cambridge_toolbar.js').read_text()
official=(root/'client/src/ui/official_editor.js').read_text()
picture=(root/'client/src/ui/picture_editor.js').read_text()
css=(root/'styles.css').read_text()
exts=(root/'client/src/tiptap_extensions.js')
preview=(root/'client/src/preview_renderer.js').read_text()
libbridge=(root.parent/'worksheetlibrary/amd/src/native_editor.js').read_text()
libbrowser=(root.parent/'worksheetlibrary/amd/src/browser.js').read_text()
student=(root.parents[1]/'mod/worksheetgrader/amd/src/native_attempt.js').read_text()
assert '"@tiptap/core"' in pkg
assert '"@tiptap/react"' in pkg
assert '"@tiptap/starter-kit"' in pkg
assert '"@tiptap/extension-text-align"' in pkg
assert '"@tiptap/extension-text-style"' in pkg
assert '"react-image-crop"' in pkg
assert '"lucide-react"' in pkg
assert '"react"' in pkg and '"react-dom"' in pkg
assert exts.exists(), 'missing tiptap_extensions.js'
assert "from '@tiptap/core'" in editor
assert "from '@tiptap/react'" in editor
assert 'OfficialEditor' in editor
assert 'createRibbon' not in editor
assert 'StarterKit' in editor
assert 'onUpdate' in editor
assert 'CambridgeToolbar' in official
assert 'ui/toolbar.js' not in official
for dead in ['layout-info','review-info','view-info']:
    assert dead not in ribbon
    assert dead not in legacytoolbar
for command in ['bold','italic','underline','strike','font-family','font-size','text-color','highlight','bullet-list','ordered-list','align-left','align-center','align-right','align-justify','link','insert-picture','insert-table','question','short-answer','long-answer','answer-table','math','save','print','download-pdf']:
    assert command in cambridge, f'missing Cambridge command {command}'
assert '.chain().focus()' in cambridge
assert 'PictureEditorDialog' in cambridge
assert 'react-image-crop' in picture and 'ReactCrop' in picture
assert 'DIGIERA_NATIVE_CAMBRIDGE_RIBBON_UI_START' in css
assert '.dgn-cambridge-toolbar' in css and '.dgn-picture-dialog' in css and '.ReactCrop' in css
assert 'generateHTML' in preview
assert 'onUpdate:' in libbridge
assert 'wslib:native-document' in libbridge
assert 'imageAdapter' in libbridge
for method in ['createAsset','replaceAsset','resolvePreview','onAssetRecord']:
    assert method in libbridge
assert 'NativeEditor.renderPreview' in libbrowser
assert 'canvas.innerHTML' not in libbrowser
assert 'setProps' not in libbridge
assert 'onUpdate:' in student
assert 'imageAdapter' in student
for method in ['createAsset','replaceAsset','resolvePreview','onAssetRecord']:
    assert method in student
assert 'setProps' not in student
print('TIPTAP_CAMBRIDGE_UI_STATIC_CONTRACT=PASS')
