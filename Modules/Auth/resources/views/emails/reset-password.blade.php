<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu</title>
</head>
<body style="margin: 0; background: #f3f6f5; color: #19201f; font-family: Arial, Helvetica, sans-serif; line-height: 1.6;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0;">
        Liên kết đặt lại mật khẩu {{ $appName }} của bạn.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f3f6f5; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; overflow: hidden; border: 1px solid #d9e2df; border-radius: 16px; background: #ffffff;">
                    <tr>
                        <td style="background: #087f73; padding: 24px 32px; color: #ffffff; font-size: 22px; font-weight: 700;">
                            {{ $appName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <h1 style="margin: 0 0 16px; color: #17201f; font-size: 24px; line-height: 1.35;">Đặt lại mật khẩu</h1>

                            <p style="margin: 0 0 16px;">
                                Xin chào {{ $userName !== '' ? $userName : 'bạn' }},
                            </p>

                            <p style="margin: 0 0 24px;">
                                Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Bấm vào nút bên dưới để tạo mật khẩu mới.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 0 24px;">
                                <tr>
                                    <td style="border-radius: 10px; background: #087f73;">
                                        <a href="{{ $resetUrl }}" style="display: inline-block; padding: 13px 22px; color: #ffffff; font-weight: 700; text-decoration: none;">Đặt lại mật khẩu</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 12px; color: #536460; font-size: 14px;">
                                Liên kết này hết hạn sau <strong>{{ $expiresInMinutes }} phút</strong> và chỉ sử dụng được một lần.
                            </p>

                            <p style="margin: 0; color: #536460; font-size: 14px;">
                                Nếu bạn không yêu cầu đặt lại mật khẩu, bạn có thể bỏ qua email này. Mật khẩu hiện tại sẽ không thay đổi.
                            </p>

                            <div style="margin-top: 28px; border-top: 1px solid #e4ebe9; padding-top: 20px; color: #71807d; font-size: 12px; word-break: break-all;">
                                Nếu nút không hoạt động, hãy sao chép liên kết sau vào trình duyệt:<br>
                                <a href="{{ $resetUrl }}" style="color: #087f73;">{{ $resetUrl }}</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
