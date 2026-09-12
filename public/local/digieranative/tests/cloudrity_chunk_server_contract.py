from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SERVICE = ROOT / 'local/digieranative/classes/service/chunk_upload_service.php'
TEACHER = ROOT / 'local/worksheetlibrary/native_asset.php'
STUDENT = ROOT / 'mod/worksheetgrader/native_asset.php'


def require(condition, message):
    if not condition:
        raise AssertionError(message)


require(SERVICE.exists(), 'shared chunk_upload_service.php is required')
service = SERVICE.read_text(encoding='utf-8')
teacher = TEACHER.read_text(encoding='utf-8')
student = STUDENT.read_text(encoding='utf-8')

require('nativechunk' in service, 'shared chunk service must stage through local_digieranative/nativechunk')
require('65536' in service, 'server chunk limit must be fixed at 64 KiB')
require('5242880' in service, 'server final image cap must remain 5 MiB')
require('create_file_from_string' in service, 'chunk staging must use Moodle File API, not node-local tmp')
require('get_area_files' in service, 'chunk accumulator must re-read staged chunks from shared File API')
require('delete' in service.lower(), 'chunk accumulator must clean staged chunks')

for name, source in [('teacher', teacher), ('student', student)]:
    require('php://input' in source, f'{name} endpoint must accept raw chunk bodies')
    require('application/octet-stream' in source or "mode'" in source or 'mode"' in source,
            f'{name} endpoint must expose chunk mode')
    require('chunk_upload_service' in source, f'{name} endpoint must use the shared chunk accumulator')
    require('uploadid' in source, f'{name} endpoint must validate uploadid')
    require('chunkindex' in source and 'chunktotal' in source,
            f'{name} endpoint must validate chunk ordering metadata')
    require('filesize' in source, f'{name} endpoint must validate declared final file size')

print('CLOUDRITY_CHUNK_SERVER_CONTRACT=PASS')
