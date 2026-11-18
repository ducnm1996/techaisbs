<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiaryTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['pregnancy_start' => today()->subWeeks(20)]);
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
}
