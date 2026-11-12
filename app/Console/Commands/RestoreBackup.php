<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * NFR06 – Phục hồi dữ liệu từ bản sao lưu do spatie/laravel-backup tạo (backup:run).
 *
 * Mặc định khôi phục vào CSDL thử nghiệm "odin_restore_test" (không đụng CSDL đang chạy),
 * sau đó so sánh số dòng từng bảng với CSDL gốc và ghi nhận thời gian phục hồi (RTO).
 *
 *   php artisan backup:restore                         # bản mới nhất → odin_restore_test
 *   php artisan backup:restore --file=2026-11-12-01-30-00.zip
 *   php artisan backup:restore --database=odin --with-files --force   # khôi phục thật
 */
class RestoreBackup extends Command
{
    protected $signature = 'backup:restore
        {--file= : Tên file zip trong thư mục sao lưu (mặc định: bản mới nhất)}
        {--database=odin_restore_test : CSDL đích để khôi phục}
        {--with-files : Khôi phục cả file tải lên vào storage/app/public}
        {--force : Bỏ qua xác nhận khi ghi đè CSDL đang dùng}';

    protected $description = 'Phục hồi CSDL (và file tải lên) từ bản sao lưu, ghi nhận thời gian phục hồi (RTO)';

    public function handle(): int
    {
        $started = microtime(true);
        $connection = config('database.connections.mysql');
        $target = $this->option('database');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $target)) {
            $this->error('Tên CSDL đích không hợp lệ.');

            return self::FAILURE;
        }

        if ($target === $connection['database'] && ! $this->option('force')
            && ! $this->confirm("Ghi đè CSDL đang dùng \"{$target}\"?")) {
            return self::FAILURE;
        }

        // 1. Chọn bản sao lưu
        $disk = Storage::disk(config('backup.backup.destination.disks')[0]);
        $folder = config('backup.backup.name');
        $zips = collect($disk->files($folder))->filter(fn ($f) => str_ends_with($f, '.zip'))->sort()->values();

        if ($zips->isEmpty()) {
            $this->error("Không có bản sao lưu nào trong {$disk->path($folder)}. Chạy: php artisan backup:run");

            return self::FAILURE;
        }

        $zipPath = $this->option('file')
            ? "{$folder}/".basename($this->option('file'))
            : $zips->last();

        if (! $disk->exists($zipPath)) {
            $this->error("Không tìm thấy {$zipPath}.");

            return self::FAILURE;
        }

        $this->info('Bản sao lưu: '.$disk->path($zipPath).' (tạo lúc '.date('d/m/Y H:i:s', $disk->lastModified($zipPath)).')');

        // 2. Giải nén
        $tmp = storage_path('app/restore-temp/'.uniqid());
        File::ensureDirectoryExists($tmp);
        $zip = new ZipArchive();

        if ($zip->open($disk->path($zipPath)) !== true) {
            $this->error('Không mở được file zip.');

            return self::FAILURE;
        }

        if ($password = config('backup.backup.password')) {
            $zip->setPassword($password);
        }

        $zip->extractTo($tmp);
        $zip->close();

        $dump = collect(File::allFiles($tmp))->first(fn ($f) => str_starts_with(str_replace('\\', '/', $f->getRelativePathname()), 'db-dumps/'));

        if (! $dump) {
            File::deleteDirectory($tmp);
            $this->error('Bản sao lưu không chứa file dump CSDL.');

            return self::FAILURE;
        }

        // 3. Tạo lại CSDL đích và nạp dump
        DB::statement("DROP DATABASE IF EXISTS `{$target}`");
        DB::statement("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $binDir = rtrim((string) config('database.connections.mysql.dump.dump_binary_path'), '/\\');
        $mysql = $binDir !== '' ? $binDir.DIRECTORY_SEPARATOR.'mysql' : 'mysql';

        $this->info("Đang nạp {$dump->getFilename()} vào CSDL \"{$target}\"...");
        $process = new Process(
            [$mysql, '-h', $connection['host'], '-P', (string) $connection['port'], '-u', $connection['username'], '--default-character-set=utf8mb4', $target],
            null,
            ['MYSQL_PWD' => $connection['password']],
            fopen($dump->getPathname(), 'r'),
            3600,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            File::deleteDirectory($tmp);
            $this->error('Nạp dump thất bại: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        // 4. Khôi phục file tải lên
        $restoredFiles = 0;
        if ($this->option('with-files') && File::isDirectory("{$tmp}/public")) {
            File::copyDirectory("{$tmp}/public", storage_path('app/public'));
            $restoredFiles = count(File::allFiles("{$tmp}/public"));
        }

        File::deleteDirectory($tmp);
        $seconds = round(microtime(true) - $started, 2);

        // 5. Đối chiếu số dòng từng bảng với CSDL gốc
        $source = $connection['database'];
        $rows = [];
        $mismatch = 0;
        $tables = DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_type = "BASE TABLE" ORDER BY table_name', [$target]);

        foreach ($tables as $table) {
            $restored = DB::table("{$target}.{$table->t}")->count();
            $original = $target === $source ? $restored : DB::table("{$source}.{$table->t}")->count();
            $ok = $restored === $original;
            $mismatch += $ok ? 0 : 1;
            $rows[] = [$table->t, $original, $restored, $ok ? 'Khớp' : 'Lệch'];
        }

        $this->table(["Bảng", "Gốc ({$source})", "Khôi phục ({$target})", 'Kết quả'], $rows);

        $rto = $seconds <= 4 * 3600 ? 'ĐẠT' : 'KHÔNG ĐẠT';
        $summary = sprintf(
            '[%s] Phục hồi %s → %s: %d bảng, %d bảng lệch, %d file; thời gian %.2f giây (RTO ≤ 4 giờ: %s)',
            now()->format('Y-m-d H:i:s'),
            basename($zipPath),
            $target,
            count($rows),
            $mismatch,
            $restoredFiles,
            $seconds,
            $rto,
        );
        File::append(storage_path('logs/backup.log'), $summary.PHP_EOL);

        $mismatch === 0 ? $this->info($summary) : $this->warn($summary);

        return self::SUCCESS;
    }
}
