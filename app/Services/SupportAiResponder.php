<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** AI may answer only low-risk FAQs; every uncertain case is escalated. */
final class SupportAiResponder
{
    /** @return array{answer: string, resolved: bool} */
    public function reply(string $category, string $message): array
    {
        $key = (string) config('services.openai.api_key');
        if ($key !== '') {
            try {
                $response = Http::withToken($key)->acceptJson()->timeout(12)
                    ->post('https://api.openai.com/v1/responses', [
                        'model' => config('services.openai.support_model', 'gpt-4.1-mini'),
                        'instructions' => 'Bạn là trợ lý CSKH MedLearn. Chỉ trả lời FAQ an toàn, không xác minh/chỉnh sửa tài khoản, hoàn tiền hay quyết định thanh toán. Nếu cần tra cứu hoặc chưa chắc, trả lời đúng chuỗi ESCALATE. Trả lời tiếng Việt, ngắn gọn.',
                        'input' => "Danh mục: {$category}. Khách hỏi: {$message}",
                    ]);
                $answer = trim((string) data_get($response->json(), 'output.0.content.0.text'));
                if ($response->successful() && $answer !== '' && ! str_contains(mb_strtoupper($answer), 'ESCALATE')) {
                    return ['answer' => $answer, 'resolved' => true];
                }
            } catch (\Throwable) {
                // Provider failure follows the same safe escalation path.
            }
        }

        $text = mb_strtolower($message);
        if ($category === 'course' && (str_contains($text, 'truy cập') || str_contains($text, 'khóa học'))) {
            return ['answer' => 'Bạn có thể vào mục Classroom hoặc trang khóa học để kiểm tra quyền truy cập. Nếu đã thanh toán mà vẫn chưa thấy nội dung, mình sẽ chuyển yêu cầu cho quản trị viên kiểm tra.', 'resolved' => true];
        }
        if ($category === 'account' && (str_contains($text, 'mật khẩu') || str_contains($text, 'đăng nhập'))) {
            return ['answer' => 'Bạn có thể dùng chức năng “Quên mật khẩu” tại trang đăng nhập để đặt lại mật khẩu. Không chia sẻ mật khẩu hoặc mã xác thực trong khung chat.', 'resolved' => true];
        }

        return ['answer' => 'Mình chưa thể xử lý chính xác yêu cầu này. Mình đã chuyển cuộc trò chuyện cho quản trị viên hỗ trợ bạn.', 'resolved' => false];
    }
}
