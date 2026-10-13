<x-app-layout>
    <livewire:crm.customers.show
        :party-id="(int) $party"
        :active-tab="request()->query('tab', 'overview')"
    />
</x-app-layout>
