<x-app-layout :title="$client->name">
    <livewire:trainer.client-card :client="$client" />
    <livewire:dialogs.client-dialog />
</x-app-layout>
