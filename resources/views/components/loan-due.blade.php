@props(['loan'])

@if ($loan->due_on)
    <p {{ $attributes->class(['text-xs', 'font-medium text-red-700' => $loan->isOverdue(), 'text-ink-soft' => ! $loan->isOverdue()]) }}>
        {{ $loan->isOverdue() ? __('Overdue since :date', ['date' => $loan->due_on->toDateString()]) : __('Return by :date', ['date' => $loan->due_on->toDateString()]) }}
    </p>
@endif
