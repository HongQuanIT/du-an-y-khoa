<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum FaqCategory: string
{
    use EnumValues;

    case TaiKhoan = 'tai_khoan';
    case GoiThanhToan = 'goi_thanh_toan';
    case TinhNangHoc = 'tinh_nang_hoc';
    case NoiDungYKhoa = 'noi_dung_y_khoa';
    case KyThuatBaoMat = 'ky_thuat_bao_mat';

    public function label(): string
    {
        return match ($this) {
            self::TaiKhoan => 'Tài khoản & đăng ký',
            self::GoiThanhToan => 'Gói & thanh toán',
            self::TinhNangHoc => 'Tính năng học tập',
            self::NoiDungYKhoa => 'Nội dung y khoa',
            self::KyThuatBaoMat => 'Kỹ thuật & bảo mật',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TaiKhoan => 'person',
            self::GoiThanhToan => 'payments',
            self::TinhNangHoc => 'school',
            self::NoiDungYKhoa => 'medical_services',
            self::KyThuatBaoMat => 'security',
        };
    }
}
