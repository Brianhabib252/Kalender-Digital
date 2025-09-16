<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure at least one user exists
        $user = User::query()->first();
        if (!$user) {
            $user = User::factory()->create();
        }

        $divisions = Division::query()->pluck('id')->all();
        if (empty($divisions)) {
            $this->call(DivisionSeeder::class);
            $divisions = Division::query()->pluck('id')->all();
        }

        $today = now();
        $startMonth = $today->copy()->startOfMonth();
        $endMonth = $today->copy()->endOfMonth();

        // Create ~20 events scattered through the current month
        $titles = [
            'Rapat Koordinasi', 'Briefing Pagi', 'Sosialisasi Prosedur', 'Evaluasi Mingguan', 'Bimbingan Teknis',
            'Rapat Anggaran', 'Koordinasi Kepaniteraan', 'Pelatihan Aplikasi', 'Monitoring Layanan', 'Forum Diskusi',
            'Rapat Internal', 'Audiensi', 'Kunjungan Kerja', 'Workshop Singkat', 'Presentasi Tim',
            'Review Perkara', 'Penyusunan Laporan', 'Rapat Panitera', 'Kurikulum Pelatihan', 'Diskusi Umum',
        ];

        for ($i = 0; $i < 20; $i++) {
            $day = rand(0, $endMonth->day - 1);
            $date = $startMonth->copy()->addDays($day);
            $hour = rand(8, 15);
            $durationHours = [1, 1.5, 2, 2.5][array_rand([1,2,3,4])];

            $start = $date->copy()->setTime($hour, [0,15,30,45][array_rand([0,1,2,3])]);
            $end = (clone $start)->addMinutes((int) round($durationHours * 60));

            $event = Event::create([
                'title' => $titles[$i % count($titles)],
                'description' => 'Kegiatan terjadwal otomatis (dummy data)'.($i+1),
                'location' => ['Ruang Sidang 1','Ruang Rapat','Aula','Ruang Panitera'][array_rand([0,1,2,3])],
                'start_at' => $start,
                'end_at' => $end,
                'all_day' => false,
                'created_by' => $user->id,
            ]);

            // attach 1-2 divisions randomly
            shuffle($divisions);
            $event->divisions()->sync(array_slice($divisions, 0, rand(1, min(2, count($divisions)))));
        }
    }
}

