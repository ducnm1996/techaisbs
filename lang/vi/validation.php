<?php

return [
    'required' => 'Trường :attribute là bắt buộc.', 'string' => 'Trường :attribute phải là văn bản.', 'email' => ':attribute không đúng định dạng email.',
    'array' => ':attribute không đúng cấu trúc cho phép.', 'boolean' => ':attribute phải là bật hoặc tắt.', 'in' => ':attribute không nằm trong lựa chọn cho phép.',
    'regex' => ':attribute không đúng định dạng.', 'date' => ':attribute phải là thời gian hợp lệ.', 'date_format' => ':attribute không đúng định dạng ngày.',
    'uuid' => ':attribute phải là UUID.', 'unique' => ':attribute đã được sử dụng.', 'confirmed' => 'Nhập lại :attribute chưa khớp.', 'distinct' => ':attribute không được trùng trong một lô.',
    'integer' => ':attribute phải là số nguyên.', 'numeric' => ':attribute phải là số.', 'after_or_equal' => ':attribute phải từ :date trở đi.',
    'required_with' => 'Cần nhập :attribute cùng :values.',
    'min' => ['string' => ':attribute cần ít nhất :min ký tự.', 'array' => ':attribute cần ít nhất :min phần tử.', 'numeric' => ':attribute phải từ :min trở lên.'],
    'max' => ['string' => ':attribute không quá :max ký tự.', 'array' => ':attribute không quá :max phần tử.', 'numeric' => ':attribute không quá :max.'],
    'between' => ['numeric' => ':attribute phải trong khoảng :min đến :max.'],
    'attributes' => ['email' => 'Email', 'password' => 'Mật khẩu', 'name' => 'Tên', 'role' => 'Vai trò', 'sources' => 'Nguồn dữ liệu', 'code' => 'Mã xác thực/ghép', 'device_name' => 'Tên thiết bị', 'phone' => 'Số điện thoại', 'events' => 'Lô sự kiện', 'from' => 'Từ ngày', 'to' => 'Đến ngày'],
];
