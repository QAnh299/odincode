<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Lead;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sinh dữ liệu Lead giả phục vụ kiểm thử hiệu năng (NFR01, NFR03).
 * Chỉ chạy trên CSDL kiểm thử, ví dụ:
 *   DB_DATABASE=odin_perf php artisan db:seed --class=PerformanceLeadSeeder
 * Số lượng mặc định 100.000; đổi bằng biến môi trường PERF_LEADS.
 */
class PerformanceLeadSeeder extends Seeder
{
    public const CHUNK = 1000;

    public function run(): void
    {
        $total = (int) (env('PERF_LEADS') ?: 100000);
        $faker = Faker::create('vi_VN');
        $branches = Branch::pluck('branch_id')->all();
        $start = (int) substr(Lead::nextId(), strlen('LEAD')); // nối tiếp mã Lead đang có

        for ($done = 0; $done < $total; $done += self::CHUNK) {
            $rows = [];
            for ($i = 0; $i < min(self::CHUNK, $total - $done); $i++) {
                $method = $faker->randomElement(Lead::CONTACT_METHODS);
                $source = $faker->randomElement(Lead::SOURCES);
                // Ràng buộc chk_leads_source_url: trừ Workshop, Website ODIN, mọi nguồn đều phải có link
                $needsUrl = $method === 'Facebook' || ! in_array($source, ['Workshop', 'Website ODIN'], true);
                $rows[] = [
                    'lead_id' => 'LEAD'.($start + $done + $i),
                    'full_name' => $faker->name(),
                    'phone' => '0'.$faker->numerify('#########'),
                    'email' => $faker->safeEmail(),
                    'source_name' => $source,
                    'source_url' => $needsUrl ? 'https://facebook.com/'.$faker->userName() : null,
                    'contact_method' => $method,
                    'status' => Lead::STATUS_NEW,
                    'branch_id' => $faker->randomElement($branches),
                    'created_at' => $faker->dateTimeBetween('-6 months'),
                ];
            }
            DB::table('Leads')->insert($rows);
            $this->command->info('Đã tạo '.min($done + self::CHUNK, $total).'/'.$total.' Lead');
        }
    }
}
