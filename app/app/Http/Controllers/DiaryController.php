<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class DiaryController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        return view('dashboard', [
            'mealCount' => $user->meals()->count(),
            'checkupCount' => $user->checkups()->count(),
            'meals' => $user->meals()->latest('entry_date')->latest('id')->limit(4)->get(),
            'checkups' => $user->checkups()->latest('entry_date')->latest('id')->limit(3)->get(),
            'nextVisit' => $user->checkups()->whereDate('next_visit', '>=', today())->orderBy('next_visit')->first(),
        ]);
    }

    public function index(Request $request, string $kind)
    {
        $filter = $request->validate([
            'stage' => ['nullable', Rule::in(['1', '2', '3'])],
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        $query = $request->user()->{$kind}();
        if (! empty($filter['date'])) {
            $query->whereDate('entry_date', $filter['date']);
        }
        if (! empty($filter['stage'])) {
            $start = CarbonImmutable::parse($request->user()->pregnancy_start);
            $bounds = ['1' => [0, 97], '2' => [98, 195], '3' => [196, null]][$filter['stage']];
            $query->whereDate('entry_date', '>=', $start->addDays($bounds[0]));
            if ($bounds[1] !== null) {
                $query->whereDate('entry_date', '<=', $start->addDays($bounds[1]));
            }
        }

        return view('entries', [
            'kind' => $kind,
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
        $entry = $id ? $request->user()->{$kind}()->findOrFail($id) : null;
        $rules = [
            'entry_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:'.$request->user()->pregnancy_start->toDateString(),
            'title' => 'required|string|max:160',
            'notes' => 'nullable|string|max:10000',
        ];
        if ($kind === 'meals') {
            $rules['meal_type'] = ['required', Rule::in(array_keys(Meal::TYPES))];
        } else {
            $rules += [
                'clinic' => 'nullable|string|max:160',
                'next_visit' => 'nullable|date_format:Y-m-d|after_or_equal:entry_date',
                'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'remove_attachment' => 'nullable|boolean',
            ];
        }
        $data = $request->validate($rules);
        unset($data['attachment'], $data['remove_attachment']);
        $oldFile = $entry?->file_path;
        $newFile = null;
        if ($kind === 'checkups' && $request->boolean('remove_attachment')) {
            $data['file_path'] = null;
            $data['file_name'] = null;
        }
        if ($kind === 'checkups' && $request->hasFile('attachment')) {
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
            Storage::disk('local')->delete($oldFile);
        }

        return redirect()->route('entries.index', ['kind' => $kind])->with('success', 'Đã lưu vào nhật ký của bạn.');
    }

    public function destroy(Request $request, string $kind, int $id)
    {
        $entry = $request->user()->{$kind}()->findOrFail($id);
        $file = $kind === 'checkups' ? $entry->file_path : null;
        $entry->delete();
        if ($file) {
            Storage::disk('local')->delete($file);
        }

        return back()->with('success', 'Đã xóa mục nhật ký.');
    }

    public function attachment(Request $request, int $id)
    {
        $entry = $request->user()->checkups()->findOrFail($id);
        abort_unless($entry->file_path && Storage::disk('local')->exists($entry->file_path), 404);

        return Storage::disk('local')->download($entry->file_path, $entry->file_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function profile(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'pregnancy_start' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:1900-01-01',
        ]);
        $earliestMeal = $request->user()->meals()->min('entry_date');
        $earliestCheckup = $request->user()->checkups()->min('entry_date');
        foreach (array_filter([$earliestMeal, $earliestCheckup]) as $date) {
            if ($data['pregnancy_start'] > $date) {
                return back()->withErrors(['pregnancy_start' => 'Ngày bắt đầu phải trước hoặc cùng ngày với mục nhật ký sớm nhất.'])->withInput();
            }
        }
        $request->user()->update($data);

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }
}
