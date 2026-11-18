<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! getenv('MAM_DEMO_PASSWORD')) {
            throw new \RuntimeException('Chỉ chạy ở local và cần biến MAM_DEMO_PASSWORD.');
        }
        $user = User::firstOrCreate(['email' => 'demo@mam.local'], [
            'name' => 'Linh · minh họa',
            'password' => getenv('MAM_DEMO_PASSWORD'),
            'pregnancy_start' => today()->subDays(144),
        ]);
        foreach ([
            [0, 'breakfast', 'Yến mạch, chuối và sữa', 'Một bát yến mạch, nửa quả chuối và một ly sữa. Ghi chú minh họa.'],
            [0, 'lunch', 'Cơm, cá hấp và rau xanh', 'Ghi lại bữa trưa và cảm nhận sau khi ăn. Dữ liệu minh họa.'],
            [1, 'snack', 'Sữa chua và trái cây', 'Một phần nhỏ vào buổi chiều. Dữ liệu minh họa.'],
            [1, 'dinner', 'Canh bí đỏ và cơm gạo lứt', 'Bữa tối tại nhà. Dữ liệu minh họa.'],
        ] as [$days, $type, $title, $notes]) {
            $user->meals()->firstOrCreate(['entry_date' => today()->subDays($days)->toDateString(), 'title' => $title], [
                'meal_type' => $type, 'notes' => $notes,
            ]);
        }
        foreach ([0, 42, 84] as $days) {
            $user->checkups()->firstOrCreate([
                'entry_date' => today()->subDays($days)->toDateString(),
                'title' => 'Khám định kỳ · dữ liệu minh họa',
            ], [
                'clinic' => 'Cơ sở khám minh họa',
                'notes' => 'Đây là hồ sơ giả lập để thử ứng dụng. Bạn có thể chỉnh sửa, tải tệp của mình hoặc xóa mục này.',
                'next_visit' => $days === 0 ? today()->addDays(28)->toDateString() : null,
            ]);
        }
    }
}
