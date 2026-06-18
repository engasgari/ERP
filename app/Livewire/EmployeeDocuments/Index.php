<?php

namespace App\Livewire\EmployeeDocuments;

use App\Livewire\Concerns\ResetsPaginationOnFilterChange;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResetsPaginationOnFilterChange;
    use WithPagination;

    public string $search = '';
    public ?int $editingId = null;
    public array $form = ['employee_id' => '', 'document_type' => 'employment', 'title' => '', 'file_path' => '', 'issued_at' => '', 'expires_at' => '', 'notes' => ''];

    protected $queryString = ['search' => ['except' => ''], 'page' => ['except' => 1]];

    public function rules(): array
    {
        return [
            'form.employee_id' => 'required|exists:employees,id',
            'form.document_type' => 'required|string|max:100',
            'form.title' => 'required|string|max:255',
            'form.file_path' => 'nullable|string|max:500',
            'form.issued_at' => 'nullable|string',
            'form.expires_at' => 'nullable|string',
            'form.notes' => 'nullable|string',
        ];
    }

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $document = EmployeeDocument::findOrFail($id);
        $this->editingId = $document->id;
        $this->form = $document->only(array_keys($this->form));
        $this->form['issued_at'] = jalaliDateInputValue('', $document->issued_at);
        $this->form['expires_at'] = jalaliDateInputValue('', $document->expires_at);
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->form = ['employee_id' => '', 'document_type' => 'employment', 'title' => '', 'file_path' => '', 'issued_at' => '', 'expires_at' => '', 'notes' => ''];
    }

    public function save(): void
    {
        $data = $this->validate()['form'];
        $data['issued_at'] = $data['issued_at'] ? jalaliToGregorianDate($data['issued_at']) : null;
        $data['expires_at'] = $data['expires_at'] ? jalaliToGregorianDate($data['expires_at']) : null;
        if (($this->form['issued_at'] && ! $data['issued_at']) || ($this->form['expires_at'] && ! $data['expires_at'])) {
            session()->flash('error', 'تاریخ مدرک معتبر نیست.');
            return;
        }
        $data['created_by'] = auth()->id();
        EmployeeDocument::updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('success', 'مدرک پرسنلی ذخیره شد.');
        $this->cancel();
    }

    public function delete(int $id): void
    {
        EmployeeDocument::findOrFail($id)->delete();
        session()->flash('success', 'مدرک حذف شد.');
    }

    public function render()
    {
        $documents = EmployeeDocument::with('employee.party')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('title', 'like', '%' . $this->search . '%')
                ->orWhereHas('employee.party', fn ($party) => $party->where('name', 'like', '%' . $this->search . '%'))))
            ->latest()
            ->paginate(12);

        return view('livewire.employee-documents.index', [
            'documents' => $documents,
            'employees' => Employee::with('party')->orderBy('id')->get(),
        ]);
    }
}
