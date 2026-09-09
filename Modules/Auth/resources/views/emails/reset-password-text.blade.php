{{ $appName }} - Đặt lại mật khẩu

Xin chào {{ $userName !== '' ? $userName : 'bạn' }},

Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.

Mở liên kết sau để tạo mật khẩu mới:
{{ $resetUrl }}

Liên kết này hết hạn sau {{ $expiresInMinutes }} phút và chỉ sử dụng được một lần.

Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này. Mật khẩu hiện tại sẽ không thay đổi.
