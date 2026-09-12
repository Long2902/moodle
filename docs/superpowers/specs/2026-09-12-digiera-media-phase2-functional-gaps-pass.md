# DIGIERA Media V1 Phase 2: Functional Gaps Pass Checkpoint

- **Date**: 2026-09-12
- **Branch**: `feature/digiera-media-v1-rc1-backup-coursepublisher`
- **Environment**: PHP 8.3.33, MariaDB 12.3.3, Moodle 5.1
- **Status**: LOCAL TESTS PASS (55 tests, 310 assertions, 0 failures, 0 errors)
- **Commits**:
  - `17703f4f` feat(digiera): implement alt text, caption, update_media and metadata contracts in Phase 2 Slice 1
  - `e31395dc` feat(digiera): Modal A visual parity, rich preview renderers, and alt/caption UX in Phase 2 Slice 2
  - `221160c4` feat(digiera): resilient multi-file upload queue, loading/disabled states, and UX polish in Phase 2 Slice 3

---

## 1. Closed Functional Gaps

### A. Alt Text & Caption Lifecycle
- Extended `create_reference` and `update_reference_version` external APIs to accept `alttext` and `caption`.
- Extended `resolve_references` external API to query and return `alttext`, `caption`, and `displayprofile`.
- Filter `filter_digieramedia` and TinyMCE component render `alt` and `figcaption` attributes natively.
- Reopening existing references rehydrates `alttext` and `caption` into input controls in the preview column.

### B. In-place Reopen & Update Without Duplicate References
- When reopening an existing reference from TinyMCE, `ui.js` sets the modal mode to editing existing reference.
- Primary button switches to **"Cập nhật reference"**.
- Clicking update executes `update_reference_version` in-place on the existing `referenceuuid`, preventing duplicate reference rows in `mdl_digieramedia_reference`.
- Supports re-pointing an existing reference to a different media item via `newmediauuid`.

### C. Metadata & Visibility Management
- Added `local_digieramedia_update_media` external service:
  - Validates capabilities: `editown` (if user is creator) or `editall` for renaming.
  - Validates `managevisibility` capability for changing visibility scope (`PRIVATE`, `COURSE`, `GLOBAL`, `SHARED`).
- Front-end UI in preview column allows inline renaming with save/cancel controls and disabled/loading states.
- Visibility dropdown allows authorized users to change visibility with immediate server sync and status feedback.

### D. Storage Path Display for Admin/KTV
- External APIs `search_media` and `get_media_versions` evaluate `overridepath` or `viewall` capabilities.
- When granted, the auto-generated Cloudflare R2 `storagepath` (objectkey) is returned.
- Right preview column displays this storage path in a clean, read-only code container for troubleshooting.

### E. Rich Format-Specific Previews (Modal A Parity)
- Added format-tailored preview components in `ui.js`:
  - **PDF**: Document sheet card with red badge, page outline icon, and file details.
  - **Video**: Darkened media player box with central play badge.
  - **Audio**: Dark slate player box with animated sound equalizer graphic.
  - **Office (Word/PowerPoint/Excel)**: Format-colored badges (blue/orange/green) with document cards.
  - **Images**: Responsive image preview container.
  - **Generic**: Clean file badge and type metadata.

### F. Resilient Multi-File Upload Queue
- Transitioned from single-progress replacement to a persistent batch queue (`uploadQueue`).
- Per-file progress state (`waiting` -> `uploading` -> `verifying` -> `done` | `error`).
- Zero DOM thrashing during upload: `updateQueueProgressFast` selectively updates progress bar width and byte text directly without re-rendering the whole queue.
- Per-file error reporting with individual `[Thử lại]` retry buttons.
- Clear queue action upon batch completion.

### G. Loading & Disabled States
- Primary action button (`save`) enters disabled state with a spinner (`Đang xử lý…` / `Đang cập nhật…`) during async operations.
- `trash-media`, `restore-media`, `confirm-purge`, `save-rename`, and `replace-media` are disabled during RPC execution.
- "Chỉ lưu vào thư viện" button safely closes modal, prompting confirmation if uploads are still in progress.

### H. Edwiser RemUI Visual Contract & Accessibility
- Modal CSS is strictly scoped to `.tiny-digieramedia` and `.tiny-digieramedia-dialog`.
- Zero modification to global RemUI selectors (`.secondary-navigation`, `.nav-tabs`, `#region-main`).
- Modal geometry conforms to Modal A: 1280px / 92vw / 85vh, 3 columns.
- Left column navigation tab updates `aria-selected` dynamically.
- Dropzone supports keyboard interaction (`Enter` / `Space`).
- Status updates use `aria-live="assertive"`, upload queue uses `aria-live="polite"`.

---

## 2. Test Verification Matrix

| Test Suite | Tests | Assertions | Result |
| :--- | :--- | :--- | :--- |
| `local_digieramedia\metadata_reference_test` | 6 | 34 | **PASS** |
| `local_digieramedia\permission_matrix_test` | 2 | 61 | **PASS** |
| `local_digieramedia\recent_external_test` | 4 | 16 | **PASS** |
| `local_digieramedia\recent_service_test` | 2 | 6 | **PASS** |
| `local_digieramedia\lifecycle_service_test` | 5 | 41 | **PASS** |
| `local_digieramedia\shared_restore_manifest_test` | 6 | 19 | **PASS** |
| `local_digieramedia\backup_manifest_test` | 5 | 22 | **PASS** |
| `local_digieramedia\content_adapter_test` | 6 | 32 | **PASS** |
| `local_digieramedia\course_restore_integration_test` | 2 | 8 | **PASS** |
| `local_digieramedia\independent_copy_test` | 5 | 29 | **PASS** |
| `local_digieramedia\reference_remapper_test` | 5 | 29 | **PASS** |
| `local_digieramedia\reference_version_state_test` | 1 | 1 | **PASS** |
| `local_digieramedia\schema_test` | 1 | 4 | **PASS** |
| `local_digieramedia\usage_service_test` | 1 | 3 | **PASS** |
| `local_coursepublisher\digiera_mode_schema_test` | 4 | 5 | **PASS** |
| **TOTAL** | **55** | **310** | **100% PASS** |

---

## 3. Operator Browser Verification Checklist

To be verified in browser by operator against staging/dev environment:

1. **Modal A Layout & Theme Integrity**:
   - [ ] Open TinyMCE editor on a Page / Label. Click the DIGIERA Media icon.
   - [ ] Verify modal opens with 3 columns: Nav (Left), Media List & Dropzone (Center), Preview (Right).
   - [ ] Verify RemUI header, sidebar, and global navigation are completely unchanged.
   - [ ] Verify header contains only folder icon + "Thư viện học liệu DIGIERA" + close button (no recommendation badges).

2. **Upload & Multi-File UX**:
   - [ ] Drag & drop 2 or more files (e.g. 1 PDF, 1 image) into the dropzone.
   - [ ] Verify individual progress cards appear in the upload queue with progress bar and byte counters.
   - [ ] Verify successful uploads switch to "Đã tải lên" with green badges.
   - [ ] Verify latest uploaded media is automatically selected in the right preview column.

3. **Format Previews**:
   - [ ] Select a PDF: verify red PDF sheet preview with document icon.
   - [ ] Select an image: verify image thumbnail preview.
   - [ ] Select a video: verify dark video card with play badge.
   - [ ] Select an audio: verify slate audio card with waveform graphic.
   - [ ] Select an Office file (docx/pptx/xlsx): verify office-branded badge.

4. **Alt Text & Caption Hydration**:
   - [ ] In the right column, enter Alt Text ("Sơ đồ minh họa") and Caption ("Hình 1: Kiến trúc hệ thống").
   - [ ] Click "Chèn vào bài". Verify marker inserted into editor with alt and caption.
   - [ ] Click the inserted media widget in TinyMCE and click DIGIERA icon to reopen.
   - [ ] Verify button shows "Cập nhật reference".
   - [ ] Verify Alt Text and Caption inputs are accurately rehydrated.
   - [ ] Change Caption to "Hình 1: Cập nhật mới" and click "Cập nhật reference".
   - [ ] Inspect database table `mdl_digieramedia_reference`: verify row count remains identical (no duplicate created).

5. **Metadata Edit & Visibility (Teacher vs Admin/KTV)**:
   - [ ] Click "Đổi tên": rename file inline and click "Lưu". Verify name updates in list and preview.
   - [ ] Change Visibility dropdown from "COURSE" to "GLOBAL". Verify immediate save and status alert.
   - [ ] Log in as Admin or KTV: verify "Đường dẫn lưu trữ (tự sinh)" displays the R2 objectkey.
   - [ ] Log in as standard Teacher: verify storage path is hidden.

6. **Versioning & Lifecycle**:
   - [ ] Click "Thay thế file" and upload a new version.
   - [ ] Verify version dropdown updates to v2, v3, etc.
   - [ ] Switch version mode to "Ghim phiên bản này" (PINNED_VERSION) and select v1. Verify reference persists pin.
   - [ ] Click "Thùng rác": verify file moves to Trash.
   - [ ] Switch to "Thùng rác" tab: verify item appears; click "Khôi phục" -> file returns to Library.
