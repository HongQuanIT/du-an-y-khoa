<?php

declare(strict_types=1);

/*
| Vietnamese validation messages. Only the rules actually used are listed —
| anything missing falls back to the framework's English strings.
*/

return [
    'accepted' => 'Bạn cần đồng ý với :attribute.',
    'boolean' => ':attribute phải là đúng hoặc sai.',
    'confirmed' => ':attribute nhập lại không khớp.',
    'email' => ':attribute không đúng định dạng.',
    'max' => [
        'string' => ':attribute không được vượt quá :max ký tự.',
    ],
    'min' => [
        'string' => ':attribute phải có ít nhất :min ký tự.',
    ],
    'password' => [
        'letters' => ':attribute phải chứa ít nhất một chữ cái.',
        'mixed' => ':attribute phải chứa cả chữ in hoa và chữ thường.',
        'numbers' => ':attribute phải chứa ít nhất một chữ số.',
        'symbols' => ':attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':attribute đã từng bị lộ trong các vụ rò rỉ dữ liệu. Vui lòng chọn mật khẩu khác.',
    ],
    'required' => 'Vui lòng nhập :attribute.',
    'string' => ':attribute phải là một chuỗi ký tự.',
    'unique' => ':attribute này đã được sử dụng.',

    'attributes' => [
        'email' => 'Email',
        'name' => 'Họ và tên',
        'password' => 'Mật khẩu',
        'password_confirmation' => 'Mật khẩu nhập lại',
        'terms' => 'điều khoản sử dụng',
    ],
];
