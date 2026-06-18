@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500']) }}>
