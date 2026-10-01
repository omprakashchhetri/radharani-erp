<?php
namespace App\Livewire\Website;

use App\Livewire\Concerns\WithDataTable;
use App\Services\PhotoCompressionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Shared list + modal behaviour for the website's categories and
 * collections (both: name, URL slug, blurb, cover photo, order, active).
 * Nothing here is ever hard-deleted from the list; a group is switched
 * off instead, which removes it from the site without losing its setup.
 */
abstract class GroupManager extends Component
{
    use WithDataTable, WithFileUploads;

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $slug = '';
    public string $blurb = '';
    public int $sort_order = 0;
    public bool $is_active = true;
    public ?string $currentImage = null;
    public $photo = null;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function imageDirectory(): string;

    protected function sortableColumns(): array
    {
        return ['order' => 'sort_order', 'name' => 'name'];
    }

    protected function defaultSort(): array
    {
        return ['order', 'asc'];
    }

    protected function rules(): array
    {
        $table = (new ($this->model()))->getTable();

        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique($table, 'slug')->ignore($this->editingId)],
            'blurb' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'photo' => ['nullable', 'image', 'max:8192'],
        ];
    }

    protected $messages = [
        'slug.regex' => 'Use lower-case letters, numbers and single hyphens, e.g. temple-gold.',
    ];

    public function create(): void
    {
        $this->resetForm();
        $this->sort_order = (int) $this->model()::max('sort_order') + 1;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $group = $this->model()::findOrFail($id);
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->slug = $group->slug;
        $this->blurb = (string) $group->blurb;
        $this->sort_order = (int) $group->sort_order;
        $this->is_active = (bool) $group->is_active;
        $this->currentImage = $group->image_url;
        $this->loadExtra($group);
        $this->showForm = true;
    }

    // The URL slug follows the name until someone edits the slug directly
    // (or the group already exists: its links may be shared by then).
    public function updatedName(): void
    {
        if (! $this->editingId) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $this->slug = Str::slug($this->slug ?: $this->name);
        $this->validate();

        if ($this->photo && ! PhotoCompressionService::available()) {
            $this->addError('photo', 'This server can\'t process photos yet (PHP\'s GD extension is off).');

            return;
        }

        $data = [
            'name' => trim($this->name),
            'slug' => $this->slug,
            'blurb' => trim($this->blurb) ?: null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ] + $this->extraData();

        $group = $this->editingId ? $this->model()::findOrFail($this->editingId) : new ($this->model());

        if ($this->photo) {
            $old = $group->image;
            $data['image'] = app(PhotoCompressionService::class)->store($this->photo, $this->imageDirectory());
            if ($old && ! preg_match('#^https?://#', $old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $group->fill($data)->save();

        $wasEditing = (bool) $this->editingId;
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: $wasEditing ? "{$group->name} updated." : "{$group->name} added.", type: 'success');
    }

    public function toggleActive(int $id): void
    {
        $group = $this->model()::findOrFail($id);
        $group->update(['is_active' => ! $group->is_active]);
        $this->dispatch('toast', message: $group->is_active ? "{$group->name} is back on the website." : "{$group->name} is hidden from the website.", type: 'success');
    }

    protected function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'slug', 'blurb', 'sort_order', 'currentImage', 'photo']);
        $this->is_active = true;
        $this->resetExtra();
    }

    protected function loadExtra(Model $group): void {}

    protected function resetExtra(): void {}

    protected function extraData(): array
    {
        return [];
    }
}
