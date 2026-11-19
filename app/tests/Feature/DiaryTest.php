<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PrivateUploads;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiaryTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['pregnancy_start' => today()->subWeeks(20)]);
    }

    public function test_homepage_and_journal_are_separate_pages(): void
    {
        $owner = $this->owner();
        $owner->meals()->create(['entry_date' => today(), 'title' => 'Kỷ niệm trong timeline', 'meal_type' => 'diary']);
        $this->actingAs($owner)->get('/')->assertOk()->assertViewIs('home')
            ->assertSee('data-home-stage', false)->assertSee('data-baby-growth', false)
            ->assertDontSee('Kỷ niệm trong timeline')->assertDontSee('Dòng thời gian');
        $this->get('/diary/meals')->assertOk()->assertViewIs('journal')
            ->assertSee('Kỷ niệm trong timeline')->assertSee('Dòng thời gian')
            ->assertDontSee('data-home-stage', false)->assertDontSee('data-baby-growth', false);
        $this->get('/diary/checkups')->assertOk()->assertDontSee('data-home-stage', false);
    }

    public function test_homepage_baby_age_rolls_over_at_midnight_and_includes_childhood(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 23:59:00', 'Asia/Ho_Chi_Minh'));
        $user = User::factory()->create(['pregnancy_start' => '2026-07-01']);
        $this->actingAs($user)->get('/')
            ->assertOk()->assertSee('20 tuần 0 ngày')->assertSee('data-days="140"', false)
            ->assertSee('data-stage="1"', false);
        $this->travelTo(CarbonImmutable::parse('2026-11-19 00:01:00', 'Asia/Ho_Chi_Minh'));
        $this->get('/')->assertSee('20 tuần 1 ngày')->assertSee('data-days="141"', false);
        $user->update(['pregnancy_start' => '2020-01-01']);
        $this->get('/')->assertOk()->assertSee('Hình dừng ở tuổi 18.')
            ->assertSee('18 tuổi')->assertSee('Bé gái')->assertSee('data-birth="2020-10-07"', false);
        $this->travelBack();
    }

    public function test_growth_preferences_are_private_persistent_and_validated(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $birth = today()->subDays(2)->toDateString();
        $this->actingAs($owner)->put('/baby-growth', ['baby_gender' => 'girl', 'birth_date' => $birth])
            ->assertRedirect('/');
        Storage::disk('local')->assertExists('growth/'.$owner->id.'.json');
        $this->get('/')->assertOk()->assertSee('data-gender="girl"', false)
            ->assertSee('data-birth="'.$birth.'"', false)->assertSee('data-estimated="false"', false);
        $this->actingAs($this->owner())->get('/')->assertSee('data-gender="boy"', false)
            ->assertSee('data-estimated="true"', false);
        $this->actingAs($owner)->put('/baby-growth', ['baby_gender' => 'invalid', 'birth_date' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors(['baby_gender', 'birth_date']);
        $this->put('/baby-growth', ['baby_gender' => 'boy', 'birth_date' => $owner->pregnancy_start->subDay()->toDateString()])
            ->assertSessionHasErrors('birth_date');
        $this->post('/logout');
        $this->put('/baby-growth', ['baby_gender' => 'boy'])->assertRedirect('/login');
    }

    public function test_guests_cannot_read_or_write_a_diary(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/diary/meals')->assertRedirect('/login');
        $this->post('/diary/checkups', [])->assertRedirect('/login');
        $this->get('/attachments/1')->assertRedirect('/login');
    }

    public function test_registration_login_and_logout(): void
    {
        $this->post('/register', [
            'name' => 'Mẹ thử nghiệm', 'email' => 'test@example.com',
            'password' => 'MyPrivateDiary2026', 'password_confirmation' => 'MyPrivateDiary2026',
            'pregnancy_start' => today()->subWeeks(12)->toDateString(),
        ])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->get('/')->assertOk()->assertSee('Mẹ thử nghiệm');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'MyPrivateDiary2026'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_entries_and_attachments_are_isolated_between_users(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $other = $this->owner();
        $this->actingAs($owner)->post('/diary/checkups', [
            'entry_date' => today()->toDateString(), 'title' => 'Hồ sơ riêng',
            'attachment' => UploadedFile::fake()->createWithContent('record.pdf', "%PDF-1.4\nTest medical document"),
        ])->assertSessionHasNoErrors()->assertRedirect('/diary/checkups');
        $record = $owner->checkups()->firstOrFail();
        Storage::disk('local')->assertExists($record->file_path);
        $this->get('/attachments/'.$record->id)->assertDownload('record.pdf');
        $this->actingAs($other)->get('/diary/checkups')->assertOk()->assertDontSee('Hồ sơ riêng');
        $this->get('/attachments/'.$record->id)->assertNotFound();
        $this->get('/diary/checkups/'.$record->id.'/edit')->assertNotFound();
        $this->put('/diary/checkups/'.$record->id, [])->assertNotFound();
        $this->delete('/diary/checkups/'.$record->id)->assertNotFound();
        $this->actingAs($owner)->delete('/diary/checkups/'.$record->id)->assertRedirect();
        Storage::disk('local')->assertMissing($record->file_path);
    }

    public function test_meal_crud_and_stage_boundaries(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->post('/diary/meals', [
            'entry_date' => $user->pregnancy_start->copy()->addDays(97)->toDateString(),
            'meal_type' => 'breakfast', 'title' => 'Bữa sáng tuần 13',
        ])->assertSessionHasNoErrors();
        $meal = $user->meals()->firstOrFail();
        $this->get('/diary/meals?stage=1')->assertSee('Bữa sáng tuần 13');
        $this->get('/diary/meals?stage=2')->assertDontSee('Bữa sáng tuần 13');
        $this->put('/diary/meals/'.$meal->id, [
            'entry_date' => $user->pregnancy_start->copy()->addDays(98)->toDateString(),
            'meal_type' => 'lunch', 'title' => 'Bữa trưa tuần 14',
        ])->assertSessionHasNoErrors();
        $this->get('/diary/meals?stage=2')->assertSee('Bữa trưa tuần 14');
        $this->get('/diary/meals?stage=4')->assertSessionHasErrors('stage');
        $this->delete('/diary/meals/'.$meal->id)->assertRedirect();
        $this->assertDatabaseMissing('meals', ['id' => $meal->id]);
    }

    public function test_executable_upload_is_rejected(): void
    {
        Storage::fake('local');
        $file = tmpfile();
        fwrite($file, '<?php echo "unsafe";');
        $path = stream_get_meta_data($file)['uri'];
        $this->actingAs($this->owner())->post('/diary/checkups', [
            'entry_date' => today()->toDateString(), 'title' => 'Invalid file',
            'attachment' => new UploadedFile($path, 'fake.pdf', null, null, true),
        ])->assertSessionHasErrors('attachment');
        $this->assertDatabaseCount('checkups', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        fclose($file);
    }

    public function test_home_timeline_private_photos_and_separate_composer(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $other = $this->owner();
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jV7kAAAAASUVORK5CYII=');
        $this->actingAs($owner)->post('/diary/meals', [
            'entry_date' => today()->toDateString(),
            'title' => 'Một ngày đáng nhớ',
            'notes' => 'Những dòng nhật ký của tôi.',
            'photos' => [UploadedFile::fake()->createWithContent('memory.png', $image)],
        ])->assertSessionHasNoErrors()->assertRedirect('/diary/meals');
        $entry = $owner->meals()->firstOrFail();
        $this->assertSame('diary', $entry->meal_type);
        $this->assertCount(1, $entry->photos());
        $photo = $entry->photos()[0];
        $url = route('journal.photo', ['id' => $entry->id, 'photo' => $photo]);
        $this->get('/diary/meals')->assertOk()
            ->assertSee('Viết nhật ký')->assertSee('Dòng thời gian')
            ->assertSee('Một ngày đáng nhớ')->assertSee($url, false)
            ->assertDontSee('Nhật ký thực phẩm')
            ->assertDontSee('id="write-diary"', false)
            ->assertDontSee('name="notes"', false)
            ->assertSee(route('entries.create', ['kind' => 'meals']), false);
        $this->get('/diary/meals/create')->assertOk()
            ->assertSee('id="write-diary"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('name="photos[]"', false);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->get('/diary/meals')->assertDontSee('Một ngày đáng nhớ');
        $this->put('/diary/meals/'.$entry->id, [])->assertNotFound();
        $this->actingAs($owner)->delete('/diary/meals/'.$entry->id)->assertRedirect();
        Storage::disk('local')->assertMissing($entry->photoDirectory().'/'.$photo);
        $this->get($url)->assertNotFound();
    }

    public function test_journal_photo_limit_and_image_replacement(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jV7kAAAAASUVORK5CYII=');
        $photos = [];
        for ($i = 0; $i < 4; $i++) {
            $photos[] = UploadedFile::fake()->createWithContent("memory-$i.png", $image);
        }
        $this->actingAs($owner)->post('/diary/meals', [
            'entry_date' => today()->toDateString(), 'title' => 'Bài viết cũ', 'photos' => $photos,
        ])->assertSessionHasNoErrors();
        $entry = $owner->meals()->firstOrFail();
        $oldPhoto = $entry->photos()[0];
        $this->put('/diary/meals/'.$entry->id, [
            'entry_date' => today()->toDateString(), 'title' => 'Chưa được lưu',
            'photos' => [UploadedFile::fake()->createWithContent('new.png', $image)],
        ])->assertSessionHasErrors('photos');
        $this->assertSame('Bài viết cũ', $entry->fresh()->title);
        $this->assertCount(4, $entry->photos());
        $this->put('/diary/meals/'.$entry->id, [
            'entry_date' => today()->toDateString(), 'title' => 'Bài viết đã sửa',
            'remove_photos' => [$oldPhoto],
            'photos' => [UploadedFile::fake()->createWithContent('new.png', $image)],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Bài viết đã sửa', $entry->fresh()->title);
        $this->assertCount(4, $entry->photos());
        Storage::disk('local')->assertMissing($entry->photoDirectory().'/'.$oldPhoto);
        $this->delete('/diary/meals/'.$entry->id);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_journal_rejects_non_image_uploads(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner())->post('/diary/meals', [
            'entry_date' => today()->toDateString(), 'title' => 'Ảnh không hợp lệ',
            'photos' => [UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\nPDF document")],
        ])->assertSessionHasErrors('photos.0');
        $this->assertDatabaseCount('meals', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_uploads_survive_replacing_the_deployment_directory(): void
    {
        $root = sys_get_temp_dir().'/mam-upload-test-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($root.'/release-one/private', 0700, true);
        File::ensureDirectoryExists($root.'/release-two/private', 0700, true);
        config([
            'filesystems.migrate_legacy_uploads' => true,
            'filesystems.disks.local.root' => $root.'/persistent',
            'filesystems.disks.legacy_uploads.root' => $root.'/release-one/private',
        ]);
        Storage::forgetDisk(['local', 'legacy_uploads']);
        try {
            $owner = $this->owner();
            $entry = $owner->meals()->create([
                'entry_date' => today()->toDateString(), 'meal_type' => 'diary', 'title' => 'Kỷ niệm của tôi',
            ]);
            $photo = str_repeat('a', 40).'.png';
            $path = $entry->photoDirectory().'/'.$photo;
            Storage::disk('legacy_uploads')->put($path, 'original-photo-bytes');
            Storage::disk('legacy_uploads')->put('checkups/1/report.pdf', 'original-report-bytes');
            $uploads = app(PrivateUploads::class);
            $uploads->migrateLegacy();
            $this->assertSame('original-photo-bytes', Storage::disk('local')->get($path));
            $this->assertSame('original-report-bytes', Storage::disk('local')->get('checkups/1/report.pdf'));
            Storage::disk('local')->put($path, 'updated-photo-bytes');
            $uploads->migrateLegacy();
            $this->assertSame('updated-photo-bytes', Storage::disk('local')->get($path));

            File::deleteDirectory($root.'/release-one');
            config(['filesystems.disks.legacy_uploads.root' => $root.'/release-two/private']);
            Storage::forgetDisk('legacy_uploads');
            $uploads->migrateLegacy();
            $this->assertSame([$photo], $entry->photos());
            $url = route('journal.photo', ['id' => $entry->id, 'photo' => $photo]);
            $this->actingAs($owner)->get($url)->assertOk()->assertStreamedContent('updated-photo-bytes');
            $this->delete('/diary/meals/'.$entry->id)->assertRedirect();
            Storage::disk('local')->assertMissing($path);
        } finally {
            File::deleteDirectory($root);
            Storage::forgetDisk(['local', 'legacy_uploads']);
        }
    }

    public function test_journal_filters_are_collapsed_until_opened_or_applied(): void
    {
        $this->actingAs($this->owner());
        $html = $this->get('/diary/meals')->assertOk()->getContent();
        preg_match('/<details class="journal-filters"[^>]*>/', $html, $closed);
        $this->assertNotEmpty($closed);
        $this->assertStringNotContainsString(' open', $closed[0]);
        $this->assertStringContainsString('>Lọc</summary>', $html);
        $filtered = $this->get('/diary/meals?stage=2')->assertOk()->getContent();
        preg_match('/<details class="journal-filters"[^>]*>/', $filtered, $open);
        $this->assertStringContainsString(' open', $open[0]);
    }

    public function test_dates_before_pregnancy_start_are_allowed_and_classified(): void
    {
        $owner = $this->owner();
        $date = $owner->pregnancy_start->copy()->subDays(10)->toDateString();
        $this->actingAs($owner)->post('/diary/meals', [
            'entry_date' => $date, 'title' => 'Kỷ niệm trước thai kỳ',
        ])->assertSessionHasNoErrors()->assertRedirect('/diary/meals');
        $this->post('/diary/checkups', [
            'entry_date' => $date, 'title' => 'Lần khám trước mốc',
        ])->assertSessionHasNoErrors();
        $this->get('/diary/meals?stage=before')->assertOk()->assertSee('Kỷ niệm trước thai kỳ')->assertSee('Trước mốc thai kỳ');
        $this->get('/diary/meals?stage=1')->assertDontSee('Kỷ niệm trước thai kỳ');
        $this->get('/diary/checkups?stage=before')->assertSee('Lần khám trước mốc');
        $this->post('/diary/meals', [
            'entry_date' => today()->addDay()->toDateString(), 'title' => 'Ngày chưa đến',
        ])->assertSessionHasErrors('entry_date');
        $this->assertDatabaseMissing('meals', ['title' => 'Ngày chưa đến']);
        $this->get('/diary/meals/create')->assertSee('min="1900-01-01"', false);
    }

    public function test_profile_updates_name_and_pregnancy_date_with_existing_older_entries(): void
    {
        $owner = $this->owner();
        $hash = $owner->password;
        $owner->meals()->create([
            'entry_date' => today()->subDays(8)->toDateString(), 'meal_type' => 'diary', 'title' => 'Bài nhật ký cũ',
        ]);
        $this->actingAs($owner)->put('/profile', [
            'name' => 'Tên hiển thị mới', 'email' => $owner->email, 'pregnancy_start' => today()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Tên hiển thị mới', $owner->fresh()->name);
        $this->assertSame($hash, $owner->fresh()->password);
        $this->assertSame(today()->toDateString(), $owner->fresh()->pregnancy_start->toDateString());
        $this->get('/diary/meals?stage=before')->assertSee('Bài nhật ký cũ');
        $this->get('/profile')->assertSee('name="email"', false)->assertSee('name="current_password"', false);
    }

    public function test_profile_credentials_can_be_changed_with_current_password(): void
    {
        $owner = $this->owner();
        $owner->update(['password' => 'InitialSecret2026']);
        $oldEmail = $owner->email;
        $this->actingAs($owner)->put('/profile', [
            'name' => $owner->name,
            'email' => 'updated@example.com',
            'pregnancy_start' => $owner->pregnancy_start->toDateString(),
            'current_password' => 'InitialSecret2026',
            'password' => 'NewPrivatePassword2026',
            'password_confirmation' => 'NewPrivatePassword2026',
        ])->assertSessionHasNoErrors();
        $this->assertSame('updated@example.com', $owner->fresh()->email);
        $this->assertNull($owner->fresh()->email_verified_at);
        $this->assertTrue(Hash::check('NewPrivatePassword2026', $owner->fresh()->password));
        $this->post('/logout');
        $this->post('/login', ['email' => $oldEmail, 'password' => 'InitialSecret2026'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'updated@example.com', 'password' => 'NewPrivatePassword2026'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_profile_rejects_wrong_current_password_and_duplicate_email(): void
    {
        $owner = $this->owner();
        $owner->update(['password' => 'InitialSecret2026']);
        $other = $this->owner();
        $fields = ['name' => $owner->name, 'email' => 'other-new@example.com', 'pregnancy_start' => $owner->pregnancy_start->toDateString()];
        $this->actingAs($owner)->put('/profile', $fields)->assertSessionHasErrors('current_password');
        $this->put('/profile', [...$fields, 'current_password' => 'wrong-password'])->assertSessionHasErrors('current_password');
        $this->put('/profile', [...$fields, 'email' => $other->email, 'current_password' => 'InitialSecret2026'])->assertSessionHasErrors('email');
        $this->assertSame($owner->email, $owner->fresh()->email);
        $this->assertTrue(Hash::check('InitialSecret2026', $owner->fresh()->password));
    }
}
