<?php

declare(strict_types=1);

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Models\Faq;
use Modules\Admin\Support\Enums\FaqCategory;

final class FaqSeeder extends Seeder
{
    public function run(): void
    {
        if (Faq::query()->exists()) {
            return;
        }

        $items = [
            [
                'category' => FaqCategory::TaiKhoan,
                'question' => 'Làm sao để đăng ký tài khoản?',
                'answer' => '<p>Để đăng ký tài khoản, bạn vui lòng nhấp vào nút "Đăng ký" ở góc trên cùng bên phải của trang web. Sau đó, điền đầy đủ thông tin cá nhân bao gồm họ tên, địa chỉ email, và mật khẩu. Bạn cũng có thể đăng ký nhanh bằng tài khoản Google hoặc Facebook. Sau khi hoàn tất, hệ thống sẽ gửi một email xác nhận đến địa chỉ email bạn đã cung cấp.</p>',
                'sort_order' => 10,
            ],
            [
                'category' => FaqCategory::TaiKhoan,
                'question' => 'Tôi quên mật khẩu thì phải làm thế nào?',
                'answer' => '<p>Nếu bạn quên mật khẩu, hãy nhấp vào liên kết "Quên mật khẩu?" tại trang Đăng nhập. Nhập địa chỉ email mà bạn đã dùng để đăng ký tài khoản. Hệ thống sẽ gửi cho bạn một email chứa liên kết để đặt lại mật khẩu mới.</p>',
                'sort_order' => 20,
            ],
            [
                'category' => FaqCategory::GoiThanhToan,
                'question' => 'MedPro có hỗ trợ thanh toán qua chuyển khoản không?',
                'answer' => '<p>Có, chúng tôi hiện đang hỗ trợ thanh toán qua hình thức chuyển khoản ngân hàng. Khi bạn tiến hành thanh toán, hãy chọn phương thức "Chuyển khoản ngân hàng". Tài khoản của bạn sẽ được kích hoạt trong vòng 1–2 giờ làm việc.</p>',
                'sort_order' => 10,
            ],
            [
                'category' => FaqCategory::TaiKhoan,
                'question' => 'Tôi có thể thay đổi địa chỉ email đăng nhập không?',
                'answer' => '<p>Hiện tại, vì lý do bảo mật, bạn không thể tự ý thay đổi địa chỉ email đã đăng ký. Nếu bạn thực sự cần thay đổi email do lý do đặc biệt, vui lòng liên hệ trực tiếp với bộ phận Chăm sóc khách hàng và cung cấp các thông tin xác minh cần thiết.</p>',
                'sort_order' => 30,
            ],
        ];

        $now = now();

        foreach ($items as $item) {
            Faq::query()->create([
                'category' => $item['category'],
                'question' => $item['question'],
                'answer' => $item['answer'],
                'sort_order' => $item['sort_order'],
                'is_published' => true,
                'published_at' => $now,
            ]);
        }
    }
}
