<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Services\PrivateUploads;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class DiaryController extends Controller
{
    public function __construct(private readonly PrivateUploads $uploads) {}

    public function dashboard(Request $request)
    {
        return $this->index($request, 'meals');
    }

    public function index(Request $request, string $kind)
    {
        $filter = $request->validate([
            'stage' => ['nullable', Rule::in(['before', '1', '2', '3'])],
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        $query = $request->user()->{$kind}();
        if (! empty($filter['date'])) {
            $query->whereDate('entry_date', $filter['date']);
        }
        if (! empty($filter['stage'])) {
            $start = CarbonImmutable::parse($request->user()->pregnancy_start);
            if ($filter['stage'] === 'before') {
                $query->whereDate('entry_date', '<', $start);
            } else {
                $bounds = ['1' => [0, 97], '2' => [98, 195], '3' => [196, null]][$filter['stage']];
                $query->whereDate('entry_date', '>=', $start->addDays($bounds[0]));
                if ($bounds[1] !== null) {
                    $query->whereDate('entry_date', '<=', $start->addDays($bounds[1]));
                }
            }
        }

        return view($kind === 'meals' ? 'journal' : 'entries', [
            'kind' => $kind,
            'babyGrowth' => json_decode(Storage::disk('local')->get('growth/'.$request->user()->id.'.json') ?? '{}', true) ?: [],
            'entries' => $query->latest('entry_date')->latest('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function form(Request $request, string $kind, ?int $id = null)
    {
        $entry = $id ? $request->user()->{$kind}()->findOrFail($id) : null;

        return view('entry-form', compact('kind', 'entry'));
    }

    public function save(Request $request, string $kind, ?int $id = null)
    {
        if ($kind === 'meals') {
            return $this->saveJournal($request, $id);
        }
        $entry = $id ? $request->user()->{$kind}()->findOrFail($id) : null;
        $rules = [
            'entry_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
            'title' => 'required|string|max:160',
            'notes' => 'nullable|string|max:10000',
        ];
        $rules += [
            'clinic' => 'nullable|string|max:160',
            'next_visit' => 'nullable|date_format:Y-m-d|after_or_equal:entry_date',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'remove_attachment' => 'nullable|boolean',
        ];
        $data = $request->validate($rules);
        unset($data['attachment'], $data['remove_attachment']);
        $oldFile = $entry?->file_path;
        $newFile = null;
        if ($request->boolean('remove_attachment')) {
            $data['file_path'] = null;
            $data['file_name'] = null;
        }
        if ($request->hasFile('attachment')) {
            $newFile = $request->file('attachment')->store('checkups/'.$request->user()->id, 'local');
            if (! $newFile) {
                return back()->withInput()->withErrors(['attachment' => 'Chưa lưu được tệp. Bạn thử lại nhé.']);
            }
            $data['file_path'] = $newFile;
            $data['file_name'] = mb_substr(basename($request->file('attachment')->getClientOriginalName()), 0, 200);
        }
        try {
            $entry ? $entry->update($data) : $request->user()->{$kind}()->create($data);
        } catch (Throwable $error) {
            if ($newFile) {
                Storage::disk('local')->delete($newFile);
            }
            throw $error;
        }
        if ($oldFile && array_key_exists('file_path', $data) && $data['file_path'] !== $oldFile) {
            $this->uploads->delete($oldFile);
        }

        return redirect()->route('entries.index', ['kind' => $kind])->with('success', 'Đã lưu vào nhật ký của bạn.');
    }

    public function destroy(Request $request, string $kind, int $id)
    {
        $entry = $request->user()->{$kind}()->findOrFail($id);
        $file = $kind === 'checkups' ? $entry->file_path : null;
        $entry->delete();
        if ($file) {
            $this->uploads->delete($file);
        }
        if ($kind === 'meals') {
            $this->uploads->deleteDirectory($entry->photoDirectory());
        }

        return back()->with('success', 'Đã xóa mục nhật ký.');
    }

    public function attachment(Request $request, int $id)
    {
        $entry = $request->user()->checkups()->findOrFail($id);
        abort_unless($entry->file_path, 404);
        $disk = $this->uploads->diskFor($entry->file_path);
        abort_unless($disk->exists($entry->file_path), 404);

        return $disk->download($entry->file_path, $entry->file_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function photo(Request $request, int $id, string $photo)
    {
        $entry = $request->user()->meals()->findOrFail($id);
        $path = $entry->photoDirectory().'/'.$photo;
        $disk = $this->uploads->diskFor($path);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function saveJournal(Request $request, ?int $id): RedirectResponse
    {
        if ($id !== null) {
            $request->user()->meals()->findOrFail($id);
        }
        $data = $request->validate([
            'entry_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
            'title' => 'required|string|max:160',
            'notes' => 'nullable|string|max:10000',
            'meal_type' => ['nullable', Rule::in([...array_keys(Meal::TYPES), 'diary'])],
            'photos' => 'nullable|array|max:'.Meal::MAX_PHOTOS,
            'photos.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_photos' => 'nullable|array|max:'.Meal::MAX_PHOTOS,
            'remove_photos.*' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{40}\.(jpg|jpeg|png|webp)$/'],
        ]);
        $uploads = $request->file('photos', []);
        $requestedRemovals = $data['remove_photos'] ?? [];
        unset($data['photos'], $data['remove_photos']);
        $data['meal_type'] ??= 'diary';
        $stored = [];
        $removed = [];

        try {
            DB::transaction(function () use ($request, $id, $data, $uploads, $requestedRemovals, &$stored, &$removed) {
                $entry = $id ? $request->user()->meals()->lockForUpdate()->findOrFail($id) : null;
                $existingPhotos = $entry?->photos() ?? [];
                $selectedRemovals = array_intersect($existingPhotos, $requestedRemovals);
                if (count($existingPhotos) - count($selectedRemovals) + count($uploads) > Meal::MAX_PHOTOS) {
                    throw ValidationException::withMessages(['photos' => 'Mỗi bài nhật ký có tối đa 4 ảnh. Bạn gỡ bớt ảnh cũ trước khi thêm ảnh mới nhé.']);
                }
                if ($entry) {
                    $entry->update($data);
                } else {
                    $entry = $request->user()->meals()->create($data);
                }
                foreach ($uploads as $photo) {
                    $path = $photo->store($entry->photoDirectory(), 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['photos' => 'Chưa lưu được ảnh. Bạn thử lại nhé.']);
                    }
                    $stored[] = $path;
                }
                foreach ($selectedRemovals as $photo) {
                    $removed[] = $entry->photoDirectory().'/'.$photo;
                }
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($stored);
            throw $error;
        }
        $this->uploads->delete($removed);

        return redirect()->route('dashboard')->with('success', 'Đã lưu nhật ký của bạn.');
    }

    public function growth(Request $request)
    {
        $data = $request->validate([
            'baby_gender' => ['required', Rule::in(['boy', 'girl'])],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after:'.$request->user()->pregnancy_start->toDateString()],
        ]);
        if (! Storage::disk('local')->put('growth/'.$request->user()->id.'.json', json_encode($data, JSON_THROW_ON_ERROR))) {
            throw ValidationException::withMessages(['birth_date' => 'Chưa lưu được thông tin của bé. Bạn thử lại nhé.']);
        }

        return redirect()->route('dashboard')->with('success', 'Đã lưu thông tin minh họa của bé.');
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower($request->input('email'))]);
        }
        $credentialsChanged = $request->input('email') !== $user->email || $request->filled('password');
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
            'pregnancy_start' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
            'password' => ['nullable', 'confirmed', 'max:200', Password::min(12)],
            'current_password' => ['nullable', Rule::requiredIf($credentialsChanged), 'current_password:web'],
        ]);
        unset($data['current_password']);
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $user->setRememberToken(Str::random(60));
        }
        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }
        $user->fill($data)->save();
        if ($credentialsChanged) {
            $request->session()->regenerate();
            $request->session()->regenerateToken();
        }

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }
}
