<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chuyển Students từ "1 bản ghi / lần đăng ký" → "1 bản ghi / người":
 *   - Thêm lead_id FK (UNIQUE) → học viên cũ đăng ký lại vẫn là cùng một bản ghi
 *   - Giữ opportunity_id nhưng bỏ UNIQUE → chỉ còn là "cơ hội tạo ra học viên này"
 *     (dùng cho báo cáo tính theo salesperson)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Thêm lead_id (nullable trước để có thể populate)
        Schema::table('Students', function (Blueprint $table) {
            $table->string('lead_id', 10)->nullable()->after('opportunity_id');
            $table->foreign('lead_id', 'fk_students_lead_id')
                ->references('lead_id')->on('Leads')
                ->onDelete('restrict')->onUpdate('restrict');
        });

        // 2. Điền lead_id từ opportunity
        DB::statement(
            'UPDATE Students st
             INNER JOIN Opportunities o ON o.opportunity_id = st.opportunity_id
             SET st.lead_id = o.lead_id'
        );

        // 3. Nếu tồn tại nhiều bản ghi Student cùng Lead (dữ liệu cũ sai):
        //    xóa bản mới hơn (student_id lớn hơn), giữ bản cũ nhất để opportunity_id vẫn trỏ đúng cơ hội đầu tiên
        DB::statement(
            'DELETE s1 FROM Students s1
             INNER JOIN Students s2
               ON s1.lead_id = s2.lead_id AND s1.student_id > s2.student_id'
        );

        // 4. Đặt lead_id NOT NULL + UNIQUE
        Schema::table('Students', function (Blueprint $table) {
            $table->string('lead_id', 10)->nullable(false)->change();
            $table->unique('lead_id', 'uq_students_lead');
        });

        // 5. Bỏ UNIQUE trên opportunity_id (giữ cột + FK cho báo cáo)
        Schema::table('Students', function (Blueprint $table) {
            $table->dropUnique('uq_students_opportunity');
        });
    }

    public function down(): void
    {
        Schema::table('Students', function (Blueprint $table) {
            $table->unique('opportunity_id', 'uq_students_opportunity');
            $table->dropUnique('uq_students_lead');
            $table->dropForeign('fk_students_lead_id');
            $table->dropColumn('lead_id');
        });
    }
};
