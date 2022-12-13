<?php

namespace PowerComponents\LivewirePowerGrid\Traits;

use Illuminate\Support\{Arr, Carbon, Collection};
use PowerComponents\LivewirePowerGrid\Column;

trait HasFilter
{
    public Collection $makeFilters;

    public array $filters = [];

    public array $enabledFilters = [];

    public array $select = [];

    public array $inputTextOptions = [];

    public function clearFilter(string $field = '', bool $emit = true): void
    {
        if (str_contains($field, '.')) {
            list($table, $column) = explode('.', $field);

            if (isset($this->filters['multi_select'][$table][$column])) {
                $this->dispatchBrowserEvent('pg:clear_multi_select::' . $this->tableName);
            }

            unset($this->filters['input_text'][$table][$column]);
            unset($this->filters['input_text_options'][$table][$column]);
            unset($this->filters['number'][$table][$column]['start']);
            unset($this->filters['number'][$table][$column]['end']);
            unset($this->filters['boolean'][$table][$column]);
            unset($this->filters['input_date_picker'][$table][$column]);
            unset($this->filters['select'][$table][$column]);
            unset($this->filters['multi_select'][$table][$column]);

            if (empty($this->filters['input_text'][$table])) {
                unset($this->filters['input_text'][$table]);
            }

            if (empty($this->filters['input_text_options'][$table])) {
                unset($this->filters['input_text_options'][$table]);
            }

            if (empty($this->filters['number_start'][$table])) {
                unset($this->filters['number_start'][$table]);
            }

            if (empty($this->filters['number'][$table]['end'])) {
                unset($this->filters['number'][$table]['end']);
            }

            if (empty($this->filters['boolean'][$table])) {
                unset($this->filters['boolean'][$table]);
            }

            if (empty($this->filters['input_date_picker'][$table])) {
                unset($this->filters['input_date_picker'][$table]);
            }

            if (empty($this->filters['select'][$table])) {
                unset($this->filters['select'][$table]);
            }

            if (empty($this->filters['multi_select'][$table])) {
                unset($this->filters['multi_select'][$table]);
            }
        } else {
            if (isset($this->filters['multi_select'][$field])) {
                $this->dispatchBrowserEvent('pg:clear_multi_select::' . $this->tableName);
            }

            unset($this->filters['input_text'][$field]);
            unset($this->filters['input_text_options'][$field]);
            unset($this->filters['number'][$field]['start']);
            unset($this->filters['number'][$field]['end']);
            unset($this->filters['boolean'][$field]);
            unset($this->filters['input_date_picker'][$field]);
            unset($this->filters['select'][$field]);
            unset($this->filters['multi_select'][$field]);
            unset($this->filters['date_picker'][$field]);
        }

        unset($this->enabledFilters[$field]);

        if ($emit) {
            $this->emit('pg:events', ['event' => 'clearFilters', 'field' => $field, 'tableName' => $this->tableName]);
        }

        $this->persistState('filters');
    }

    public function clearAllFilters(): void
    {
        $this->enabledFilters = [];
        $this->filters        = [];

        $this->emit('pg:events', ['event' => 'clearAllFilters', 'tableName' => $this->tableName]);

        $this->persistState('filters');
    }

    public static function getInputTextOptions(): array
    {
        return [
            'contains',
            'contains_not',
            'is',
            'is_not',
            'starts_with',
            'ends_with',
            'is_empty',
            'is_not_empty',
            'is_null',
            'is_not_null',
            'is_blank',
            'is_not_blank',
        ];
    }

    private function resolveFilters(): void
    {
        $makeFilters = [];

        /** @var Column $column */
        foreach ($this->columns as $column) {
            if (!isset($column->inputs)) {
                continue;
            }

            foreach ($column->inputs as $key => $input) {
                if (!isset($input['dataField'])) {
                    data_set($input, 'dataField', $column->dataField ?: $column->field);
                }
                data_set($input, 'field', $column->field);
                data_set($input, 'label', $column->title);
                $makeFilters[$key][] = $input;
            }
        }

        $this->makeFilters = collect($makeFilters);

        $path = 'livewire-powergrid::datatable.input_text_options';

        foreach (self::getInputTextOptions() as $option) {
            $this->inputTextOptions[$option] = "$path.$option";
        }
    }

    public function getLabelFromMakeFilters(string $filterType, string $field): string
    {
        /** @var array $makeFilter */
        $makeFilter = $this->makeFilters->toArray()[$filterType];

        $filter = collect($makeFilter)
            ->filter(
                fn ($filter) => strval(data_get($filter, 'dataField', strval(data_get($filter, 'field')))) === $field
            )
                ->first();

        return $filter['label'];
    }

    public function datePikerChanged(array $data): void
    {
        $this->resetPage();

        $input = explode('.', $data['values']);

        $startDate = strval(data_get($data, 'selectedDates.0'));
        $endDate   = strval(data_get($data, 'selectedDates.1'));

        $timeZone = strval(config('app.timezone'));

        $startDateTime = Carbon::parse($startDate)->setTimezone($timeZone);
        $endDateTime   = Carbon::parse($endDate)->setTimezone($timeZone);

        if (!$data['enableTime']) {
            $startDateTime->startOfDay();
            $endDateTime->endOfDay();
        }

        data_set($data, 'selectedDates.0', $startDateTime);
        data_set($data, 'selectedDates.1', $endDateTime);

        $this->enabledFilters[$data['field']]['data-field'] = $data['field'];
        $this->enabledFilters[$data['field']]['label']      = $data['label'];

        if (count($input) === 3) {
            $this->filters['date_picker'][$input[2]] = $data['selectedDates'];
            $this->persistState('filters');

            return;
        }

        if (count($input) === 4) {
            $this->filters['date_picker'][$input[2] . '.' . $input[3]] = $data['selectedDates'];
            $this->persistState('filters');

            return;
        }

        if (count($input) === 5) {
            $this->filters['date_picker'][$input[2] . '.' . $input[3] . '.' . $input[4]] = $data['selectedDates'];
            $this->persistState('filters');
        }
    }

    public function multiSelectChanged(array $data): void
    {
        $this->resetPage();

        $field  = $data['id'];
        $values = $data['values'];

        unset($data['id']);
        unset($data['values']);

        $this->filters['multi_select'][$field] = $values;

        /** @var array $multiSelect */
        $multiSelect = $this->makeFilters->get('multi_select');

        /** @var array $filter */
        $filter = collect($multiSelect)->where('dataField', $field)->first();

        $this->enabledFilters[$field]['id']    = $field;
        $this->enabledFilters[$field]['label'] = $filter['label'];

        if (count($values) === 0) {
            $this->clearFilter($field, emit: false);
        }

        $this->afterChangedMultiSelectFilter($field, $values);

        $this->persistState('filters');
    }

    public function filterSelect(string $field): void
    {
        $label = $this->getLabelFromMakeFilters('select', $field);

        $this->resetPage();

        $this->enabledFilters[$field]['id']    = $field;
        $this->enabledFilters[$field]['label'] = $label;

        $value = data_get($this->filters, "select.$field");

        if (blank($value)) {
            $this->clearFilter($field, emit: false);
        }

        $this->afterChangedSelectFilter($field, $label, $value);

        $this->persistState('filters');
    }

    public function filterNumberStart(string $field, string $value): void
    {
        $label = $this->getLabelFromMakeFilters('number', $field);

        $this->resetPage();

        $this->filters['number'][$field]['start'] = $value;

        $this->enabledFilters[$field]['id']    = $field;
        $this->enabledFilters[$field]['label'] = $label;

        if (blank($value)) {
            $this->clearFilter($field, emit: false);
        }

        $this->afterChangedNumberStartFilter($field, $label, $value);

        $this->persistState('filters');
    }

    public function filterNumberEnd(string $field, string $value): void
    {
        $label = $this->getLabelFromMakeFilters('number', $field);

        $this->resetPage();

        $this->filters['number'][$field]['end'] = $value;

        $this->enabledFilters[$field]['id']    = $field;
        $this->enabledFilters[$field]['label'] = $label;

        if (blank($value)) {
            $this->clearFilter($field, emit: false);
        }

        $this->afterChangedNumberEndFilter($field, $value, $label);

        $this->persistState('filters');
    }

    public function filterInputText(string $field, string $value): void
    {
        $label = $this->getLabelFromMakeFilters('input_text', $field);

        $this->resetPage();

        $this->enabledFilters[$field]['id']    = $field;
        $this->enabledFilters[$field]['label'] = $label;

        if (blank($value)) {
            $this->clearFilter($field, emit: false);
        }

        $this->afterChangedInputTextFilter($field, $label, $value);

        $this->persistState('filters');
    }

    public function filterBoolean(string $field, string $value): void
    {
        $label = $this->getLabelFromMakeFilters('boolean', $field);

        $this->resetPage();

        $this->filters['boolean'][$field] = $value;

        $this->enabledFilters[$field]['id']    = $field;
        $this->enabledFilters[$field]['label'] = $label;

        if ($value == 'all') {
            $this->clearFilter($field, emit: false);
        }

        $this->afterChangedBooleanFilter($field, $label, $value);

        $this->persistState('filters');
    }

    public function filterInputTextOptions(string $field, string $value): void
    {
        $label = $this->getLabelFromMakeFilters('input_text_options', $field);

        data_set($this->filters, 'input_text_options.' . $field, $value);

        $this->enabledFilters[$field]['id']    = $field;
        $this->enabledFilters[$field]['label'] = $label;

        $this->resetPage();

        $this->enabledFilters[$field]['disabled'] = false;

        if (in_array($value, ['is_empty', 'is_not_empty', 'is_null', 'is_not_null', 'is_blank', 'is_not_blank'])) {
            $this->enabledFilters[$field]['disabled'] = true;
            $this->filters['input_text'][$field]      = null;
        }

        if (blank($value)) {
            $this->clearFilter($field, emit: false);
        }

        $this->persistState('filters');
    }
}
