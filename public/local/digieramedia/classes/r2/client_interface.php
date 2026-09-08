<?php
namespace local_digieramedia\r2;

interface client_interface {
    public function head_object(string $bucket, string $key): array;
    public function presign_put(string $bucket, string $key, string $contenttype, int $ttl): string;
    public function create_multipart_upload(string $bucket, string $key, string $contenttype): string;
    public function presign_upload_part(string $bucket, string $key, string $uploadid, int $partnumber, int $ttl): string;
    public function complete_multipart_upload(string $bucket, string $key, string $uploadid, array $parts): array;
    public function abort_multipart_upload(string $bucket, string $key, string $uploadid): void;
    public function delete_object(string $bucket, string $key): void;
    public function copy_object(string $sourcebucket, string $sourcekey, string $targetbucket, string $targetkey): array;
}
